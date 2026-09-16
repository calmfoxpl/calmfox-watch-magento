<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Health;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\ResourceConnection;

/**
 * Kolejki wiadomości Magento (transport bazodanowy). Tędy idą asynchroniczne
 * operacje sklepu: masowe zmiany cen, eksporty, integracje. Martwy konsument
 * oznacza sklep, który przyjmuje polecenia i po cichu ich nie wykonuje.
 *
 * Check jest OPCJONALNY i celowo ostrożny. Widzimy wyłącznie kolejki trzymane
 * w bazie: gdy sklep korzysta z RabbitMQ, tabele istnieją, ale są puste, więc
 * zielone „kolejki puste" byłoby kłamstwem o czymś, czego nie mierzymy.
 * Dlatego przy tabeli bez ani jednej wiadomości w historii check w ogóle nie
 * powstaje.
 *
 * Progi opieramy na WIEKU, nie na liczbie: przy imporcie kolejka bywa długa
 * i to mija. Wiadomości z błędem dają najwyżej `warn`, nigdy `fail`: same nie
 * znikną, więc `fail` zostawiłby stale otwarty incydent, a stale czerwony
 * monitoring uczy ludzi go ignorować.
 */
class QueueCheck implements HealthCheckInterface
{
    private const LABEL = 'Kolejki wiadomości';
    private const WARN_AFTER = 300;
    private const FAIL_AFTER = 1800;

    /** Stany z Magento\MysqlMq\Model\QueueManagement. */
    private const STATUS_NEW = 2;
    private const STATUS_IN_PROGRESS = 3;
    private const STATUS_RETRY = 5;
    private const STATUS_ERROR = 6;

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function run(): ?CheckResult
    {
        $start = microtime(true);
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('queue_message_status');
            if (!$connection->isTableExists($table)) {
                return null;
            }

            // SQL zamiast obiektu Select z tego samego powodu, co w checku crona:
            // funkcja agregująca podana kolumną zostałaby zacytowana jak nazwa pola.
            $quoted = $connection->quoteIdentifier($table);
            $waiting = implode(', ', [self::STATUS_NEW, self::STATUS_IN_PROGRESS, self::STATUS_RETRY]);

            $pending = (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE status IN (%s)', $quoted, $waiting));
            $failed = (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE status = %d', $quoted, self::STATUS_ERROR));
            $total = (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $quoted));
            $oldest = $connection->fetchOne(sprintf('SELECT MIN(updated_at) FROM %s WHERE status IN (%s)', $quoted, $waiting));
        } catch (\Throwable) {
            return null; // baza ma własny check
        }

        if (0 === $total) {
            // Tabela pusta od zawsze: albo sklep nie używa kolejek, albo używa
            // RabbitMQ. W obu wypadkach nie mamy czego mierzyć i tak mówimy.
            return null;
        }

        $age = self::age(\is_string($oldest) ? $oldest : null);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $status = CheckResult::OK;
        if (null !== $age && $age > self::FAIL_AFTER) {
            $status = CheckResult::FAIL;
        } elseif ((null !== $age && $age > self::WARN_AFTER) || $failed > 0) {
            $status = CheckResult::WARN;
        }

        return CheckResult::of($status, 'queue', self::LABEL, $this->detail($pending, $age, $failed, $status), $ms);
    }

    private function detail(int $pending, ?int $age, int $failed, string $status): string
    {
        $parts = [];
        $parts[] = 0 === $pending
            ? 'Kolejki bazodanowe puste.'
            : sprintf('W kolejce czeka %d %s, najstarsza od %s.', $pending, self::plural($pending, 'wiadomość', 'wiadomości'), self::duration($age));
        if ($failed > 0) {
            $parts[] = sprintf('Wiadomości z błędem: %d. Same nie znikną, trzeba je przejrzeć.', $failed);
        }
        if (CheckResult::FAIL === $status) {
            $parts[] = 'Tak stare zaległości znaczą zwykle, że konsumenci kolejek nie działają (bin/magento queue:consumers:start albo cron).';
        }

        return implode(' ', $parts);
    }

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

    private static function duration(?int $seconds): string
    {
        if (null === $seconds) {
            return 'nieznanego czasu';
        }
        if ($seconds < 120) {
            return sprintf('%d s', $seconds);
        }
        if ($seconds < 7200) {
            return sprintf('%d min', (int) round($seconds / 60));
        }

        return sprintf('%d godz.', (int) round($seconds / 3600));
    }

    private static function plural(int $count, string $one, string $many): string
    {
        return 1 === $count ? $one : $many;
    }
}
