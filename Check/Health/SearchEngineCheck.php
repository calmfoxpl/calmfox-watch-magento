<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Health;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\FileCache;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientInterface;

/**
 * Wyszukiwarka (Elasticsearch albo OpenSearch). W Magento 2.4 to nie jest
 * dodatek: bez działającego klastra padają wyszukiwanie, listingi kategorii
 * i nawigacja warstwowa, czyli praktycznie cała sprzedaż. Dlatego check jest
 * obowiązkowy, w odróżnieniu od pozostałych platform, gdzie wyszukiwarka bywa
 * doklejana.
 *
 * Gdy klaster wymaga uwierzytelnienia, świadomie nie wysyłamy poświadczeń
 * z konfiguracji: odpowiedź 401 też jest dowodem, że usługa żyje i odpowiada,
 * a mniej danych w tej ścieżce to mniej rzeczy do wycieku. Piszemy wtedy
 * wprost, że sprawdziliśmy dostępność, a nie stan klastra.
 */
class SearchEngineCheck implements HealthCheckInterface
{
    private const LABEL = 'Wyszukiwarka (Elasticsearch / OpenSearch)';
    private const CACHE_KEY = 'search-engine';
    private const TIMEOUT = 2;
    private const TTL = 300;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ClientInterface $httpClient,
        private readonly FileCache $cache,
    ) {
    }

    public function run(): ?CheckResult
    {
        $engine = trim((string) $this->scopeConfig->getValue('catalog/search/engine'));
        if ('' === $engine) {
            return CheckResult::warn('search_engine', self::LABEL, 'W konfiguracji sklepu nie ma wybranego silnika wyszukiwania, więc nie wiemy, co sprawdzić.');
        }

        $host = trim((string) $this->scopeConfig->getValue(sprintf('catalog/search/%s_server_hostname', $engine)));
        if ('' === $host) {
            return CheckResult::warn('search_engine', self::LABEL, sprintf(
                'Sklep korzysta z silnika „%s", ale nie znamy jego adresu (konfiguracja nie podaje hosta). Bywa tak przy wyszukiwarkach w chmurze, których stanu nie zbadamy stąd.',
                $engine
            ));
        }

        $cached = $this->cache->get(self::CACHE_KEY);
        if (\is_array($cached) && isset($cached['status'])) {
            return CheckResult::of((string) $cached['status'], 'search_engine', self::LABEL, isset($cached['detail']) ? (string) $cached['detail'] : null, isset($cached['ms']) ? (int) $cached['ms'] : null);
        }

        $result = $this->probe($this->baseUri($host, (int) $this->scopeConfig->getValue(sprintf('catalog/search/%s_server_port', $engine))), $engine);
        $this->cache->set(self::CACHE_KEY, $result, self::TTL);

        return CheckResult::of($result['status'], 'search_engine', self::LABEL, $result['detail'], $result['ms']);
    }

    /** @return array{status: string, detail: string, ms: int} */
    private function probe(string $uri, string $engine): array
    {
        $start = microtime(true);
        try {
            $this->httpClient->setTimeout(self::TIMEOUT);
            $this->httpClient->get($uri.'/_cluster/health');
            $code = $this->httpClient->getStatus();
            $body = json_decode((string) $this->httpClient->getBody(), true);
        } catch (\Throwable $e) {
            return ['status' => CheckResult::FAIL, 'detail' => sprintf('Klaster nie odpowiada: %s', $e->getMessage()), 'ms' => self::ms($start)];
        }

        $ms = self::ms($start);
        if (\in_array($code, [401, 403], true)) {
            return [
                'status' => CheckResult::OK,
                'detail' => sprintf('Silnik %s odpowiada, ale wymaga uwierzytelnienia, więc sprawdzamy wyłącznie dostępność usługi, nie stan klastra.', $engine),
                'ms' => $ms,
            ];
        }
        if (200 !== $code) {
            return ['status' => CheckResult::FAIL, 'detail' => sprintf('Klaster odpowiada kodem %d. Wyszukiwarka i listingi kategorii przestaną działać.', $code), 'ms' => $ms];
        }

        $cluster = \is_array($body) && \is_string($body['status'] ?? null) ? $body['status'] : '';

        return [
            'status' => '' === $cluster || 'red' === $cluster ? CheckResult::FAIL : ('yellow' === $cluster ? CheckResult::WARN : CheckResult::OK),
            'detail' => '' === $cluster ? 'Odpowiedź klastra bez pola status.' : sprintf('Stan klastra: %s (silnik %s).', $cluster, $engine),
            'ms' => $ms,
        ];
    }

    /** Host z konfiguracji bywa podany razem ze schematem, a bywa samą nazwą. */
    private function baseUri(string $host, int $port): string
    {
        $host = rtrim($host, '/');
        if (!preg_match('#^https?://#i', $host)) {
            $host = 'http://'.$host;
        }
        if ($port > 0 && 1 !== preg_match('#:\d+$#', $host)) {
            $host .= ':'.$port;
        }

        return $host;
    }

    private static function ms(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
