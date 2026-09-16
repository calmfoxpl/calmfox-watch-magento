<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Magento składa moduł z plików XML, a błąd w nich wychodzi dopiero na żywej
 * instalacji, po `setup:upgrade`: pozycja nie pojawia się w menu, layout nie
 * znajduje bloku, ekran oddaje 404 na brakującym uprawnieniu. Ten test
 * sprawdza to, co da się sprawdzić bez Magento: czy pliki mówią o sobie
 * nawzajem prawdę.
 */
final class WiringTest extends TestCase
{
    private static function xml(string $relative): \SimpleXMLElement
    {
        $path = \dirname(__DIR__).'/'.$relative;
        self::assertFileExists($path);
        $xml = simplexml_load_file($path);
        self::assertInstanceOf(\SimpleXMLElement::class, $xml, sprintf('%s nie jest poprawnym XML-em', $relative));

        return $xml;
    }

    /**
     * Pozycja stoi w GŁÓWNYM pasku panelu, nie pod „Sklepy → Ustawienia".
     * To jest decyzja produktowa, nie kosmetyka: monitoring, do którego trzeba
     * się doklikać przez dwa poziomy, ogląda wyłącznie ten, kto go szuka.
     */
    public function testMenuItemSitsInTheMainBar(): void
    {
        $item = self::xml('etc/adminhtml/menu.xml')->menu->add;

        self::assertSame('Calmfox_Watch::watch', (string) $item['id']);
        self::assertNull($item['parent'], 'pozycja wróciła pod cudzą gałąź, a ma stać w pasku głównym');
        self::assertSame('calmfox_watch/watch/index', (string) $item['action']);
        self::assertSame('Calmfox_Watch::watch', (string) $item['resource']);
    }

    /** Uprawnienie z menu musi istnieć w ACL, inaczej Magento pozycji nie pokaże nikomu. */
    public function testMenuResourceExistsInAcl(): void
    {
        $resources = self::xml('etc/acl.xml')->xpath('//resource[@id="Calmfox_Watch::watch"]');

        self::assertNotEmpty($resources, 'zasób z menu zniknął z acl.xml');
    }

    /** Kafelek na pulpicie: layout wskazuje blok i szablon, które naprawdę są w module. */
    public function testDashboardWidgetPointsToExistingBlockAndTemplate(): void
    {
        $block = self::xml('view/adminhtml/layout/adminhtml_dashboard_index.xml')->body->referenceContainer->block;

        self::assertSame('content', (string) self::xml('view/adminhtml/layout/adminhtml_dashboard_index.xml')->body->referenceContainer['name']);
        $class = str_replace('Calmfox\\Watch\\', '', (string) $block['class']);
        self::assertFileExists(\dirname(__DIR__).'/'.str_replace('\\', '/', $class).'.php');

        [, $template] = explode('::', (string) $block['template'], 2);
        self::assertFileExists(\dirname(__DIR__).'/view/adminhtml/templates/'.$template);
    }

    /** Ikona w menu: arkusz dokładany do każdego ekranu panelu musi istnieć razem ze znakiem marki. */
    public function testMenuStylesheetAndMarkAreShippedWithTheModule(): void
    {
        $css = (string) self::xml('view/adminhtml/layout/default.xml')->head->css['src'];
        [, $file] = explode('::', $css, 2);

        $path = \dirname(__DIR__).'/view/adminhtml/web/'.$file;
        self::assertFileExists($path);
        self::assertFileExists(\dirname(__DIR__).'/view/adminhtml/web/images/mark.svg');
        self::assertStringContainsString('../images/mark.svg', (string) file_get_contents($path));
    }
}
