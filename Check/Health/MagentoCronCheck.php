<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Health;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\ResourceConnection;

/**
 * Zadania cykliczne Magento. W sklepie to nie jest szczegół techniczny: cron
 * wysyła maile o zamówieniach, przelicza reguły cenowe, generuje mapy stron
 * i sprząta koszyki. Martwy cron potrafi milczeć tygodniami, bo strona wygląda
 * normalnie, a sklep po prostu przestaje robić to, czego nikt nie ogląda.
 *
 * Magento ma własny harmonogram w tabeli cron_schedule, więc nie potrzebujemy
 * tu odbijanego znacznika (jak w Syliusie): pytamy wprost, kiedy ostatnie
 * zadanie skończyło się powodzeniem. Progi liczymy z WIEKU, nie z liczby
 * zadań: przy imporcie cennika kolejka bywa długa i to jeszcze nie awaria.
 */
class MagentoCronCheck implements HealthCheckInterface
{
    private const LABEL = 'Zadania cykliczne (cron)';
    private const WARN_AFTER = 900;
    private const FAIL_AFTER = 3600;

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function run(): ?CheckResult
    {
        $start = microtime(true);
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('cron_schedule');
            if (!$connection->isTableExists($table)) {
                return null; // instalacja bez modułu Magento_Cron: nie ma czego sprawdzać
            }

            // Zapytania składamy jako SQL, a nie obiektem Select: funkcja
            // agregująca podana kolumną zostałaby zacytowana jak nazwa pola
            // (`tabela`.`COUNT(*)`), a klasa wyrażeń zmieniała w 2.4 przestrzeń
            // nazw. quote() i quoteIdentifier() są w każdym wydaniu te same.
            $quoted = $connection->quoteIdentifier($table);
            $since = $connection->quote(gmdate('Y-m-d H:i:s', time() - 86400));

            $lastSuccess = $connection->fetchOne(sprintf(
                'SELECT MAX(finished_at) FROM %s WHERE status = %s',
                $quoted,
                $connection->quote('success')
            ));
            $errors = (int) $connection->fetchOne(sprintf(
                'SELECT COUNT(*) FROM %s WHERE status = %s AND created_at >= %s',
                $quoted,
                $connection->quote('error'),
                $since
            ));
            $missed = (int) $connection->fetchOne(sprintf(
                'SELECT COUNT(*) FROM %s WHERE status = %s AND created_at >= %s',
                $quoted,
                $connection->quote('missed'),
                $since
            ));
        } catch (\Throwable) {
            // Baza ma własny check i to on ma zapalić się na czerwono.
            return null;
        }

        $ms = (int) round((microtime(true) - $start) * 1000);
        $age = self::age(\is_string($lastSuccess) ? $lastSuccess : null);

        if (null === $age) {
            return CheckResult::warn('magento_cron', self::LABEL,
                'W harmonogramie nie ma ani jednego zakończonego powodzeniem zadania. Sprawdź, czy w cronie systemowym stoi bin/magento cron:run: bez niego sklep nie wyśle maili o zamówieniach ani nie przeliczy reguł cenowych.', $ms);
        }

        $status = CheckResult::OK;
        if ($age > self::FAIL_AFTER) {
            $status = CheckResult::FAIL;
        } elseif ($age > self::WARN_AFTER || $errors > 0) {
            $status = CheckResult::WARN;
        }

        return CheckResult::of($status, 'magento_cron', self::LABEL, $this->detail($age, $errors, $missed, $status), $ms);
    }

    private function detail(int $age, int $errors, int $missed, string $status): string
    {
        $parts = [sprintf('Ostatnie zadanie zakończone powodzeniem %s temu.', self::duration($age))];
        if ($errors > 0) {
            $parts[] = sprintf('Zadań zakończonych błędem w ostatniej dobie: %d.', $errors);
        }
        if ($missed > 0) {
            $parts[] = sprintf('Pominiętych: %d (zwykle znaczy, że cron nie nadąża albo chodzi rzadziej niż co minutę).', $missed);
        }
        if (CheckResult::FAIL === $status) {
            $parts[] = 'Cron prawdopodobnie nie działa: maile o zamówieniach, reguły cenowe i sprzątanie koszyków stoją.';
        }

        return implode(' ', $parts);
    }

    /** Znaczniki w cron_schedule Magento zapisuje w UTC. */
    private static function age(?string $value): ?int
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }
        try {
            $at = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }

        return max(0, time() - $at->getTimestamp());
    }

    private static function duration(int $seconds): string
    {
        if ($seconds < 120) {
            return sprintf('%d s', $seconds);
        }
        if ($seconds < 7200) {
            return sprintf('%d min', (int) round($seconds / 60));
        }
        if ($seconds < 172800) {
            return sprintf('%d godz.', (int) round($seconds / 3600));
        }

        return sprintf('%d dni', (int) round($seconds / 86400));
    }
}
