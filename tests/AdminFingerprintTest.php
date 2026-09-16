<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\AdminFingerprint;
use PHPUnit\Framework\TestCase;

final class AdminFingerprintTest extends TestCase
{
    private const SECRET = '0123456789abcdef0123456789abcdef';

    public function testFingerprintIgnoresOrderAndFitsTheContract(): void
    {
        $first = AdminFingerprint::of(['1:admin', '7:magazyn'], self::SECRET);
        $second = AdminFingerprint::of(['7:magazyn', '1:admin'], self::SECRET);

        self::assertSame($first, $second, 'Kolejność kont z bazy nie jest gwarantowana, a odcisk ma opisywać zbiór.');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $first, 'Hub odrzuca odciski spoza tego formatu.');
    }

    public function testAnyChangeOfTheSetChangesTheFingerprint(): void
    {
        $before = AdminFingerprint::of(['1:admin', '7:magazyn'], self::SECRET);

        self::assertNotSame($before, AdminFingerprint::of(['1:admin', '7:magazyn', '9:nowy'], self::SECRET), 'Nowe konto to zmiana składu.');
        self::assertNotSame($before, AdminFingerprint::of(['1:admin', '7:sprzedaz'], self::SECRET), 'Zmiana loginu przy tej samej liczbie kont też.');
        self::assertNotSame($before, AdminFingerprint::of(['1:admin'], self::SECRET));
    }

    /**
     * Sól z sekretu instalacji sprawia, że odcisku nie da się porównać ze
     * słownikiem odcisków popularnych loginów ani zestawić między sklepami.
     */
    public function testTheSameAccountsGiveDifferentFingerprintsInDifferentShops(): void
    {
        self::assertNotSame(
            AdminFingerprint::of(['1:admin'], self::SECRET),
            AdminFingerprint::of(['1:admin'], 'fedcba9876543210fedcba9876543210')
        );
    }
}
