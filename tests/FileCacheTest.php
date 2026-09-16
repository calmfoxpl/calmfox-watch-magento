<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\FileCache;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/calmfox-watch-cache-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,.}*', \GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->dir);
    }

    public function testValueSurvivesBetweenInstancesUntilItExpires(): void
    {
        (new FileCache($this->dir))->set('payload-health', ['status' => 'ok'], 60);

        self::assertSame(['status' => 'ok'], (new FileCache($this->dir))->get('payload-health'));
    }

    public function testExpiredValueIsGoneAndMissingKeyIsNull(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('krotki', 'wartość', -1);

        self::assertNull($cache->get('krotki'), 'Wpis po terminie nie ma prawa wrócić.');
        self::assertNull($cache->get('nie-ma-takiego'));
    }

    public function testDeleteRemovesOnlyTheGivenKey(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('payload-health', 1, 60);
        $cache->set('payload-security', 2, 60);
        $cache->delete('payload-health');

        self::assertNull($cache->get('payload-health'));
        self::assertSame(2, $cache->get('payload-security'));
    }

    public function testUnwritableDirectoryDoesNotThrow(): void
    {
        // Brak miejsca na pamięć podręczną nie może wywrócić odpowiedzi:
        // adres kontrolny ma odpowiedzieć nawet wtedy, gdy dysk jest pełny.
        $cache = new FileCache('/nie-ma-takiego-katalogu/calmfox');
        $cache->set('klucz', 'wartość', 60);

        self::assertNull($cache->get('klucz'));
    }
}
