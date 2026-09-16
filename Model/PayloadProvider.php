<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

use Calmfox\Watch\Check\CheckRunner;
use Calmfox\Watch\Core\FileCache;
use Calmfox\Watch\Core\InstalledPackages;
use Calmfox\Watch\Core\PayloadBuilder;
use Calmfox\Watch\Core\StateStore;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Złożenie odpowiedzi obu sekcji razem z pamięcią podręczną: `health` 60 s,
 * `security` 10 minut. Bez tego sonda odpytująca co minutę kazałaby sklepowi
 * liczyć rozmiar katalogu, pukać do serwera poczty i przeglądać indeksy przy
 * każdym sprawdzeniu.
 *
 * Pamięć podręczna leży w plikach obok stanu, nie w pamięci Magento: adres
 * kontrolny ma odpowiadać także wtedy, gdy padnie Redis albo baza, a
 * `cache:flush` przy wdrożeniu nie ma prawa kasować pomiarów.
 */
class PayloadProvider
{
    public const SECTION_HEALTH = 'health';
    public const SECTION_SECURITY = 'security';

    private const HEALTH_TTL = 60;
    private const SECURITY_TTL = 600;

    /** Metapakiety wydania: stąd bierzemy wersję platformy, gdy nie poda jej Magento. */
    private const EDITION_PACKAGES = ['magento/product-community-edition', 'magento/product-enterprise-edition'];

    public function __construct(
        private readonly CheckRunner $healthChecks,
        private readonly CheckRunner $securityChecks,
        private readonly AdminSignals $signals,
        private readonly ModuleSignals $modules,
        private readonly UpdateHistory $history,
        private readonly StateStore $state,
        private readonly FileCache $cache,
        private readonly Paths $paths,
        private readonly ProductMetadataInterface $productMetadata,
    ) {
    }

    /** @return array<string, mixed> */
    public function payload(string $section, bool $fresh = false): array
    {
        $section = self::SECTION_SECURITY === $section ? self::SECTION_SECURITY : self::SECTION_HEALTH;
        $key = 'payload-'.$section;

        if (!$fresh) {
            $cached = $this->cache->get($key);
            if (\is_array($cached) && isset($cached['status'])) {
                return $cached;
            }
        }

        if (self::SECTION_SECURITY === $section) {
            // Sekcja security i tak liczy się drożej, więc to najtańsze miejsce
            // na uzgodnienie migawki wersji.
            $this->history->reconcile();
            $result = $this->securityChecks->run();
            $payload = PayloadBuilder::security(Version::NUMBER, $result['checks'], $this->history->all());
            $this->cache->set($key, $payload, self::SECURITY_TTL);

            return $payload;
        }

        $result = $this->healthChecks->run();
        $payload = PayloadBuilder::health(
            Version::NUMBER,
            $result['checks'],
            PayloadBuilder::site($this->platformVersion(), \PHP_VERSION, Version::NUMBER, $this->updates()),
            // Dwa zestawy sygnałów w jednym polu `signals`: konta z pełnym dostępem
            // i skład włączonych modułów. Hub porównuje je osobno, ale odczyt jest
            // jeden, bo to jedno odpytanie.
            array_merge($this->signals->signals(), $this->modules->signals())
        );
        $this->cache->set($key, $payload, self::HEALTH_TTL);

        return $payload;
    }

    public function forget(): void
    {
        $this->cache->delete('payload-'.self::SECTION_HEALTH);
        $this->cache->delete('payload-'.self::SECTION_SECURITY);
    }

    /**
     * Wersja platformy. Magento potrafi zwrócić „UNKNOWN" (instalacja z gałęzi
     * deweloperskiej albo z rozbitych pakietów), więc w takim wypadku pytamy
     * spis Composera zamiast wysyłać do panelu słowo, które niczego nie znaczy.
     */
    private function platformVersion(): ?string
    {
        try {
            $version = trim((string) $this->productMetadata->getVersion());
        } catch (\Throwable) {
            $version = '';
        }
        if ('' !== $version && 0 !== strcasecmp($version, 'UNKNOWN')) {
            return ltrim($version, 'vV');
        }

        $packages = InstalledPackages::load($this->paths->vendor());
        foreach (self::EDITION_PACKAGES as $name) {
            if (isset($packages[$name])) {
                return ltrim($packages[$name], 'vV');
            }
        }

        return null;
    }

    /**
     * Liczby zaległych aktualizacji albo `null`, gdy nikt ich nie policzył.
     * `null` oznacza pominięcie CAŁEGO pola `updates` w payloadzie, zgodnie
     * z kontraktem: zero to informacja „sprawdzone", a nie „nie wiemy".
     *
     * @return array{core: int, plugins: int, themes: int}|null
     */
    private function updates(): ?array
    {
        $stored = $this->state->get('updates');
        if (!\is_array($stored) || !isset($stored['plugins'])) {
            return null;
        }

        return [
            'core' => (int) ($stored['core'] ?? 0),
            'plugins' => (int) ($stored['plugins'] ?? 0),
            'themes' => 0,
        ];
    }
}
