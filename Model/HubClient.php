<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

use Calmfox\Watch\Core\SecretManager;
use Calmfox\Watch\Core\StateStore;
use Magento\Framework\HTTP\ClientInterface;

/**
 * Rozmowa z API Calmfox Watch. Podczas rejestracji i parowania hub wykonuje
 * challenge: pobiera NASZ adres kontrolny na domenie sklepu i oczekuje echa
 * znacznika. To dlatego znacznik zapisujemy przed wysłaniem żądania, a limit
 * czasu jest długi (hub w trakcie obsługi puka do nas).
 */
class HubClient
{
    public const CMS = 'Magento';

    /** Hub w trakcie obsługi żądania odpytuje nasz adres kontrolny. */
    private const PAIRING_TIMEOUT = 25;
    private const DISCONNECT_TIMEOUT = 8;
    private const LOOPBACK_TIMEOUT = 5;

    /**
     * Ocena jedzie z gotowej migawki, więc hub odpowiada od razu i nie puka do nas.
     * Limit jest krótki celowo: to jedyne wyjście do sieci podczas RYSOWANIA ekranu,
     * a panel administracyjny nie ma prawa wisieć, kiedy hub milczy.
     */
    private const SCORE_TIMEOUT = 8;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly ClientInterface $loopbackClient,
        private readonly SecretManager $secrets,
        private readonly StateStore $state,
        private readonly Settings $settings,
    ) {
    }

    /**
     * Aktywacja pakietu Free wprost ze sklepu: konto, strona i monitoring
     * po stronie huba, link logowania na podany adres.
     *
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function register(string $email): array
    {
        $result = $this->call('/api/public/plugin/register', [
            'domain' => $this->domain(),
            'email' => $email,
            'healthUrl' => $this->healthUrl(),
            'nonce' => $this->secrets->makePairingNonce(),
            'cms' => self::CMS,
        ], 201, self::PAIRING_TIMEOUT);
        $this->secrets->clearPairingNonce();

        if ($result['ok']) {
            $this->savePaired($result['data']);
            $result['message'] = 'Konto Free jest aktywne. Sprawdź skrzynkę, wysłaliśmy link logowania do panelu.';
        }

        return $result;
    }

    /**
     * Parowanie z istniejącą stroną kluczem instalacyjnym z ekranu Integracje.
     * Tą samą drogą idzie wymiana sekretu: hub zapamiętuje nowy adres kontrolny.
     *
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function pair(string $token = ''): array
    {
        $token = '' !== $token ? $token : (string) $this->state->get('installToken', '');
        if ('' === $token) {
            return ['ok' => false, 'message' => 'Brak klucza instalacyjnego. Skopiuj go z ekranu Integracje w panelu.', 'data' => []];
        }

        $result = $this->call('/api/public/plugin/pair', [
            'token' => $token,
            'healthUrl' => $this->healthUrl(),
            'nonce' => $this->secrets->makePairingNonce(),
            'cms' => self::CMS,
        ], 200, self::PAIRING_TIMEOUT);
        $this->secrets->clearPairingNonce();

        if ($result['ok']) {
            $this->savePaired($result['data']);
            $result['message'] = 'Połączono z Calmfox Watch. Monitoring wnętrza sklepu działa.';
        }

        return $result;
    }

    /**
     * Rozłączenie. Sam klucz instalacyjny nie wystarcza (jest jawny), więc hub
     * żąda też sekretu z adresu kontrolnego, który zna wyłącznie ta instalacja.
     * Robimy to najlepszym staraniem: przy braku sieci hub i tak zauważy
     * milczący adres, ale wolimy powiedzieć wprost „kończę", niż kazać mu
     * budzić ludzi głuchym endpointem.
     *
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function disconnect(): array
    {
        $token = (string) $this->state->get('installToken', '');
        if ('' === $token) {
            return ['ok' => false, 'message' => 'Ten sklep nie jest połączony.', 'data' => []];
        }

        $result = $this->call('/api/public/plugin/disconnect', [
            'token' => $token,
            'key' => $this->secrets->secret(),
        ], 204, self::DISCONNECT_TIMEOUT);

        $this->state->set(['connected' => false, 'disconnectedAt' => gmdate('c')]);
        if ($result['ok']) {
            $result['message'] = 'Połączenie zakończone. Monitoring wnętrza sklepu został wstrzymany, klucz zostaje zapisany, więc ponowne połączenie zajmie jedno kliknięcie.';
        }

        return $result;
    }

    /**
     * Samokontrola adresu kontrolnego pętlą zwrotną. Wyłapuje reguły serwera
     * i zapory, które blokują naszą ścieżkę, zanim człowiek utknie na parowaniu.
     * Uczciwie: to test od środka serwera, dostęp z zewnątrz ostatecznie
     * potwierdza dopiero challenge huba.
     *
     * @return array{ok: bool, message: string}
     */
    public function loopbackCheck(): array
    {
        $url = $this->healthUrl();
        if ('' === $url) {
            return ['ok' => false, 'message' => 'nie znamy adresu sklepu (pusty adres bazowy w konfiguracji)'];
        }

        try {
            $this->loopbackClient->setTimeout(self::LOOPBACK_TIMEOUT);
            if (method_exists($this->loopbackClient, 'setOptions')) {
                // Certyfikat ocenia monitoring z zewnątrz. Tutaj pytamy wyłącznie,
                // czy żądanie w ogóle dociera do naszej ścieżki, a na maszynach
                // testowych i za pośrednikiem certyfikat bywa własny: fałszywe
                // ostrzeżenie na ekranie nie pomogłoby nikomu.
                $this->loopbackClient->setOptions([\CURLOPT_SSL_VERIFYPEER => false, \CURLOPT_SSL_VERIFYHOST => 0]);
            }
            $this->loopbackClient->get($url);
            $code = $this->loopbackClient->getStatus();
            $body = json_decode((string) $this->loopbackClient->getBody(), true);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if (\in_array($code, [200, 503], true) && \is_array($body) && isset($body['status'])) {
            return ['ok' => true, 'message' => ''];
        }

        return ['ok' => false, 'message' => sprintf('adres kontrolny odpowiada kodem %d albo obcym formatem', $code)];
    }

    /** Pełny sekretny adres kontrolny. To on jest zapamiętywany po stronie huba. */
    public function healthUrl(): string
    {
        $base = $this->settings->baseUrl();

        return '' === $base ? '' : $base.'/calmfox-watch/health?key='.$this->secrets->secret();
    }

    public function domain(): string
    {
        return (string) parse_url($this->settings->baseUrl(), \PHP_URL_HOST);
    }

    public function apiUrl(): string
    {
        return $this->settings->apiUrl();
    }

    public function panelUrl(): string
    {
        $stored = rtrim((string) $this->state->get('panelUrl', ''), '/');
        // Przeprowadzka panelu na watch.calmfox.net — adres zapisany przy parowaniu.
        if ('https://watch.calmfox.pl' === $stored) {
            $stored = '';
        }

        return '' !== $stored ? $stored : 'https://watch.calmfox.net';
    }

    /**
     * Adres ekranu w panelu dla TEGO sklepu. Bez identyfikatora panel otworzy się
     * na ostatnio oglądanej stronie, czyli u agencji na cudzej: stąd parametr `site`,
     * ten sam, którego używa wtyczka WordPressa.
     */
    public function panelLink(string $path = '/app/dashboard'): string
    {
        $siteId = (string) $this->state->get('siteId', '');

        return $this->panelUrl().$path.('' !== $siteId ? '?site='.rawurlencode($siteId) : '');
    }

    /** @param array<string, mixed> $data */
    private function savePaired(array $data): void
    {
        $this->state->set([
            'connected' => true,
            'installToken' => (string) ($data['installToken'] ?? $this->state->get('installToken', '')),
            'siteId' => (string) ($data['siteId'] ?? ''),
            'plan' => (string) ($data['plan'] ?? ''),
            'panelUrl' => '' !== (string) ($data['panelUrl'] ?? '') ? (string) $data['panelUrl'] : (string) $this->state->get('panelUrl', 'https://watch.calmfox.net'),
            'pairedAt' => gmdate('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    /**
     * Kondycja sklepu z panelu: jedna liczba 0–100 i podwyniki obszarów.
     *
     * To JEDYNE miejsce, w którym moduł pyta hub o coś dla siebie. Reszta kontraktu jest
     * pull — hub odpytuje nas — ale ocena powstaje po jego stronie (bierze pod uwagę uptime,
     * przeglądy podstron i pomiary wydajności, o których sklep nie ma pojęcia), więc musi
     * przyjechać stąd. Buforowaniem zajmuje się ScoreProvider, nie ta metoda.
     *
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function score(): array
    {
        $token = (string) $this->state->get('installToken', '');
        if ('' === $token) {
            return ['ok' => false, 'message' => 'Sklep nie jest połączony z Calmfox Watch.', 'data' => []];
        }

        return $this->call('/api/public/plugin/score', ['token' => $token], 200, self::SCORE_TIMEOUT);
    }

    private function call(string $path, array $body, int $expected, int $timeout): array
    {
        try {
            $this->httpClient->setTimeout($timeout);
            $this->httpClient->addHeader('Content-Type', 'application/json');
            $this->httpClient->addHeader('Accept', 'application/json');
            $this->httpClient->post($this->apiUrl().$path, (string) json_encode($body, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
            $code = $this->httpClient->getStatus();
            $raw = (string) $this->httpClient->getBody();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => sprintf('Nie udało się połączyć z Calmfox Watch: %s', $e->getMessage()), 'data' => []];
        }

        $data = json_decode($raw, true);
        $data = \is_array($data) ? $data : [];

        if ($code === $expected) {
            return ['ok' => true, 'message' => '', 'data' => $data];
        }

        foreach (['detail', 'message', 'error'] as $key) {
            if (\is_string($data[$key] ?? null) && '' !== $data[$key]) {
                return ['ok' => false, 'message' => $data[$key], 'data' => []];
            }
        }

        return ['ok' => false, 'message' => sprintf('Serwer Calmfox Watch odpowiedział kodem %d.', $code), 'data' => []];
    }
}
