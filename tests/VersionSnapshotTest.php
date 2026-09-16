<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\InstalledPackages;
use Calmfox\Watch\Core\VersionSnapshot;
use PHPUnit\Framework\TestCase;

final class VersionSnapshotTest extends TestCase
{
    private const AT = '2026-08-19T10:00:00+00:00';

    public function testUpgradeDowngradeAdditionAndRemovalAreAllRecorded(): void
    {
        $before = ['magento/framework' => '103.0.6', 'vendor/platnosci' => '1.2.0', 'vendor/znika' => '3.0.0'];
        $after = ['magento/framework' => '103.0.7', 'vendor/platnosci' => '1.2.0', 'vendor/nowy' => '0.1.0'];

        $entries = VersionSnapshot::diff($before, $after, self::AT);
        $byName = array_column($entries, null, 'name');

        self::assertCount(3, $entries, 'Niezmieniony pakiet nie tworzy wpisu.');
        self::assertSame(['103.0.6', '103.0.7'], [$byName['magento/framework']['from'], $byName['magento/framework']['to']]);
        self::assertSame('core', $byName['magento/framework']['kind'], 'Pakiet platformy idzie jako „core".');
        self::assertNull($byName['vendor/nowy']['from'], 'Nowy pakiet nie ma wersji poprzedniej.');
        self::assertNull($byName['vendor/znika']['to'], 'Usunięcie pakietu też bywa przyczyną awarii.');
        self::assertSame('plugin', $byName['vendor/platnosci']['kind'] ?? 'plugin');
    }

    public function testEditionMetapackageAndPhpCountAsPlatform(): void
    {
        $entries = VersionSnapshot::diff(
            ['magento/product-community-edition' => '2.4.6-p8', 'php' => '8.1.29'],
            ['magento/product-community-edition' => '2.4.7-p3', 'php' => '8.3.14'],
            self::AT
        );

        self::assertSame(['core', 'core'], array_column($entries, 'kind'));
        self::assertContains('PHP', array_column($entries, 'name'), 'Podbicie PHP przez hostingodawcę wywraca sklep tak samo skutecznie jak aktualizacja pakietu.');
    }

    /**
     * Composer nie zostawia śladu, kto i skąd wdrożył, więc nie zgadujemy:
     * `manual` i brak autora są uczciwsze niż wymyślony wpis.
     */
    public function testEntriesDoNotInventAnAuthor(): void
    {
        $entries = VersionSnapshot::diff(['vendor/paczka' => '1.0.0'], ['vendor/paczka' => '1.1.0'], self::AT);

        self::assertSame('manual', $entries[0]['mode']);
        self::assertNull($entries[0]['by']);
        self::assertSame(self::AT, $entries[0]['at']);
    }

    public function testMergeKeepsTheNewestFirstAndCutsAtTheContractLimit(): void
    {
        $old = [];
        for ($i = 0; $i < VersionSnapshot::MAX_ENTRIES; ++$i) {
            $old[] = ['kind' => 'plugin', 'name' => 'stary/'.$i, 'from' => '1.0.0', 'to' => '1.0.1', 'at' => self::AT, 'mode' => 'manual', 'by' => null];
        }
        $fresh = [['kind' => 'plugin', 'name' => 'nowy/pakiet', 'from' => '1.0.0', 'to' => '2.0.0', 'at' => self::AT, 'mode' => 'manual', 'by' => null]];

        $merged = VersionSnapshot::merge($fresh, $old);

        self::assertCount(VersionSnapshot::MAX_ENTRIES, $merged, 'Hub przyjmuje 200 wpisów, więc tyle trzymamy.');
        self::assertSame('nowy/pakiet', $merged[0]['name']);
    }

    public function testSnapshotAlwaysCarriesThePhpVersion(): void
    {
        $snapshot = InstalledPackages::withPlatform(['vendor/paczka' => '1.0.0'], '8.3.14');

        self::assertSame(['php' => '8.3.14', 'vendor/paczka' => '1.0.0'], $snapshot);
    }
}
