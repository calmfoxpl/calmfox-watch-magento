<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\Bytes;
use Calmfox\Watch\Core\DiskLimit;
use PHPUnit\Framework\TestCase;

final class DiskLimitTest extends TestCase
{
    /**
     * Sedno uczciwości tego checku: na hostingu współdzielonym odczyt systemowy
     * opisuje cały serwer, a nie konto klienta, więc nie wolno go pokazać.
     */
    public function testSystemReadingIsRejectedWhenItDescribesTheWholeServer(): void
    {
        self::assertTrue(DiskLimit::readingIsShared(50 * Bytes::GB, true), 'Ślad panelu hostingowego znaczy konto na współdzielonym serwerze.');
        self::assertTrue(DiskLimit::readingIsShared(50 * Bytes::GB, false, '/home/klient:/tmp'), 'open_basedir też.');
        self::assertTrue(DiskLimit::readingIsShared(4096.0 * Bytes::GB, false), '4 TB to nie jest konto hostingowe.');
        self::assertFalse(DiskLimit::readingIsShared(200 * Bytes::GB, false), 'Własny serwer: odczyt wolno pokazać.');
    }

    public function testQuotaThresholds(): void
    {
        $limit = 20.0 * Bytes::GB;

        self::assertSame('ok', DiskLimit::statusForQuota(10 * Bytes::GB, $limit));
        self::assertSame('warn', DiskLimit::statusForQuota(17.5 * Bytes::GB, $limit));
        self::assertSame('fail', DiskLimit::statusForQuota(19.5 * Bytes::GB, $limit));
        self::assertSame('ok', DiskLimit::statusForQuota(100 * Bytes::GB, 0.0), 'Bez limitu nie ma czego przekroczyć.');
    }

    public function testFreeSpaceThresholdsWatchBothPercentAndAbsoluteValue(): void
    {
        self::assertSame('ok', DiskLimit::statusForFree(100 * Bytes::GB, 200 * Bytes::GB));
        self::assertSame('warn', DiskLimit::statusForFree(15 * Bytes::GB, 200 * Bytes::GB), 'Poniżej 10% wolnego.');
        self::assertSame('fail', DiskLimit::statusForFree(2 * Bytes::GB, 200 * Bytes::GB), 'Poniżej 3% wolnego.');
        self::assertSame('fail', DiskLimit::statusForFree(150 * Bytes::MB, 10 * Bytes::GB), 'Duży procent, ale 150 MB to za mało na cokolwiek.');
    }

    public function testPercentUsedNeverDividesByZero(): void
    {
        self::assertSame(50, DiskLimit::percentUsed(10 * Bytes::GB, 20 * Bytes::GB));
        self::assertSame(0, DiskLimit::percentUsed(10 * Bytes::GB, 0.0));
    }
}
