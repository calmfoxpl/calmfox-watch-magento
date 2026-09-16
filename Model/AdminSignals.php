<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

use Calmfox\Watch\Core\AdminFingerprint;
use Calmfox\Watch\Core\SecretManager;
use Magento\Framework\App\ResourceConnection;

/**
 * Konta z dostępem do panelu. Sygnały jadą w sekcji `health` (odpytywanej
 * co minutę), a nie w `security` (raz na dobę), bo nagły przyrost kont
 * administracyjnych to klasyczny objaw przejęcia sklepu, i wolimy wiedzieć
 * o tym w ciągu minut niż nazajutrz.
 *
 * Loginów NIE wysyłamy: jedzie liczba, jednokierunkowy odcisk zbioru kont
 * (zmiana odcisku = zmiana składu) i data najnowszego konta. Kto to
 * konkretnie, właściciel sklepu widzi u siebie w panelu Magento.
 *
 * Czytamy wprost z tabeli, nie kolekcją modeli: kolekcja ładuje encje razem
 * z hasłami i tokenami sesji, a nam wystarczą trzy kolumny i chcemy, żeby
 * to zapytanie było tanie przy odpytaniu co minutę.
 */
class AdminSignals
{
    /** Tyle kont wystarczy do wykrycia zmiany składu, a zapytanie zostaje tanie. */
    private const LIMIT = 200;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly SecretManager $secrets,
    ) {
    }

    /**
     * @return array{count: int, identities: list<string>, newest: ?string, truncated: bool, available: bool}
     */
    public function snapshot(): array
    {
        $empty = ['count' => 0, 'identities' => [], 'newest' => null, 'truncated' => false, 'available' => false];

        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('admin_user');
            if (!$connection->isTableExists($table)) {
                return $empty;
            }
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['user_id', 'username', 'created'])
                    ->where('is_active = ?', 1)
                    ->order('user_id ASC')
                    ->limit(self::LIMIT + 1)
            );
        } catch (\Throwable) {
            return $empty;
        }

        $truncated = \count($rows) > self::LIMIT;
        $rows = \array_slice($rows, 0, self::LIMIT);

        $identities = [];
        $newest = null;
        foreach ($rows as $row) {
            $identities[] = (string) ($row['user_id'] ?? '').':'.(string) ($row['username'] ?? '');
            $created = self::at($row['created'] ?? null);
            if (null !== $created && (null === $newest || $created > $newest)) {
                $newest = $created;
            }
        }

        return [
            'count' => \count($identities),
            'identities' => $identities,
            'newest' => $newest?->format('c'),
            'truncated' => $truncated,
            'available' => true,
        ];
    }

    /** @return array{adminCount: ?int, adminsFingerprint: ?string, newestAdminAt: ?string} */
    public function signals(): array
    {
        $snapshot = $this->snapshot();
        if (!$snapshot['available']) {
            return ['adminCount' => null, 'adminsFingerprint' => null, 'newestAdminAt' => null];
        }

        return [
            'adminCount' => $snapshot['count'],
            'adminsFingerprint' => AdminFingerprint::of($snapshot['identities'], $this->secrets->secret()),
            'newestAdminAt' => $snapshot['newest'],
        ];
    }

    private static function at(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }
        try {
            // Magento zapisuje znaczniki kont w UTC, niezależnie od strefy sklepu.
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
