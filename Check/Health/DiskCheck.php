<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Health;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\Bytes;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\DiskLimit;
use Calmfox\Watch\Core\FileCache;
use Calmfox\Watch\Core\InstallSize;
use Calmfox\Watch\Core\StateStore;
use Calmfox\Watch\Model\Paths;

/**
 * Miejsce na dysku. Kolejność źródeł jest ta sama, co we wtyczce WordPressa
 * i wynika z jednej zasady: nie podajemy liczby, która nie dotyczy konta klienta.
 * 1) limit podany przez klienta, 2) wiarygodny odczyt systemowy (własny serwer),
 * 3) uczciwe „hosting nie pokazuje limitu tego konta". Zawsze z realnie
 * policzonym rozmiarem instalacji.
 *
 * Brak zapisu do var/, pub/media albo generated/ to `fail`: sklep, który nie
 * może zapisać pamięci podręcznej, zdjęcia produktu ani klas generowanych,
 * przestaje działać w ciągu minut. Katalog generated/ jest tu nieoczywisty,
 * ale to on wywraca Magento po wdrożeniu w trybie produkcyjnym.
 */
class DiskCheck implements HealthCheckInterface
{
    private const LABEL = 'Miejsce na dysku';
    private const CACHE_KEY = 'install-size';

    public function __construct(
        private readonly Paths $paths,
        private readonly StateStore $state,
        private readonly FileCache $cache,
    ) {
    }

    public function run(): ?CheckResult
    {
        $unwritable = [];
        foreach (['var/' => $this->paths->var(), 'pub/media' => $this->paths->media(), 'generated/' => $this->paths->generated()] as $name => $dir) {
            if ('' !== $dir && is_dir($dir) && !is_writable($dir)) {
                $unwritable[] = $name;
            }
        }
        if ([] !== $unwritable) {
            return CheckResult::fail('disk', self::LABEL, sprintf(
                'Brak prawa zapisu: %s. Sklep nie zapisze pamięci podręcznej, plików wgrywanych w panelu ani klas generowanych.',
                implode(', ', $unwritable)
            ));
        }

        $size = $this->installSize();
        $used = (float) $size['bytes'];
        $usedLabel = sprintf('Sklep zajmuje %s%s.', Bytes::format($used), $size['complete'] ? '' : ' (pomiar przerwany na limicie czasu, liczba jest zaniżona)');

        // 1) Limit konta podany przez klienta: na hostingu współdzielonym to jedyna pewna liczba.
        $quota = $this->quotaGb();
        if ($quota > 0) {
            $limit = $quota * Bytes::GB;

            return CheckResult::of(DiskLimit::statusForQuota($used, $limit), 'disk', self::LABEL, sprintf(
                '%s To %d%% z podanego limitu konta %s. Poza tym miejsce zajmują poczta i pozostałe strony na koncie.',
                $usedLabel,
                DiskLimit::percentUsed($used, $limit),
                Bytes::format($limit)
            ));
        }

        $root = $this->paths->root();
        $free = @disk_free_space($root);
        $total = @disk_total_space($root);

        // 2) Odczyt systemowy tylko wtedy, gdy naprawdę dotyczy tego konta.
        if (false !== $free && false !== $total && $total > 0
            && !DiskLimit::readingIsShared((float) $total, DiskLimit::markersPresent(), (string) ini_get('open_basedir'))) {
            return CheckResult::of(DiskLimit::statusForFree((float) $free, (float) $total), 'disk', self::LABEL, sprintf(
                'Wolne %s z %s (%d%%). %s',
                Bytes::format((float) $free),
                Bytes::format((float) $total),
                (int) floor($free / $total * 100),
                $usedLabel
            ));
        }

        // 3) Hosting współdzielony bez limitu od klienta: mówimy wprost, czego nie wiemy.
        return CheckResult::ok('disk', self::LABEL, sprintf(
            '%s Hosting nie pokazuje limitu tego konta (widzimy tylko wspólny dysk serwera), więc nie liczymy zajętości. Podaj limit na ekranie Calmfox Watch, a będziemy go pilnować.',
            $usedLabel
        ));
    }

    /** Wartość z ekranu w panelu sklepu wygrywa z wartością z konfiguracji modułu. */
    private function quotaGb(): float
    {
        $stored = $this->state->get('diskQuotaGb');

        return \is_numeric($stored) && (float) $stored > 0 ? (float) $stored : 0.0;
    }

    /** @return array{bytes: int, files: int, complete: bool} */
    private function installSize(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (\is_array($cached) && isset($cached['bytes'])) {
            return ['bytes' => (int) $cached['bytes'], 'files' => (int) ($cached['files'] ?? 0), 'complete' => (bool) ($cached['complete'] ?? true)];
        }

        $size = InstallSize::measure($this->paths->root());
        $this->cache->set(self::CACHE_KEY, $size, InstallSize::TTL);

        return $size;
    }
}
