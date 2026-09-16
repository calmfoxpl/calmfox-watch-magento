<?php

declare(strict_types=1);

namespace Calmfox\Watch\Core;

/**
 * Wersje pakietów czytane z vendor/composer/installed.php. To jedyne źródło,
 * które mówi prawdę o TYM wdrożeniu (a nie o tym, co wpisano w composer.json),
 * i jest dostępne bez uruchamiania Composera.
 *
 * Katalog vendor bywa przeniesiony (composer.json → config.vendor-dir, zdarza
 * się na wdrożeniach z osobnym katalogiem wydania), więc gdy nie ma go tam,
 * gdzie się spodziewamy, pytamy o ścieżkę sam autoloader Composera. Ten sam
 * błąd popełniliśmy raz w pakiecie dla Neosa, gdzie dystrybucja trzyma
 * zależności w Packages/Libraries.
 */
final class InstalledPackages
{
    /** @return array<string, string> nazwa pakietu => wersja */
    public static function load(string $vendorDir): array
    {
        $file = self::locate($vendorDir);
        if (null === $file) {
            return [];
        }

        /** @var mixed $data */
        $data = @include $file;
        if (!\is_array($data) || !\is_array($data['versions'] ?? null)) {
            return [];
        }

        $out = [];
        foreach ($data['versions'] as $name => $info) {
            if (!\is_string($name) || !\is_array($info)) {
                continue;
            }
            // Pakiety „replaced"/„provided" nie mają wersji własnej instalacji.
            $version = $info['pretty_version'] ?? $info['version'] ?? null;
            if (\is_string($version) && '' !== $version) {
                $out[$name] = $version;
            }
        }
        ksort($out);

        return $out;
    }

    /** Ścieżka do spisu pakietów albo null, gdy nie ma jak jej ustalić. */
    public static function locate(string $vendorDir): ?string
    {
        $file = rtrim($vendorDir, '/').'/composer/installed.php';
        if (is_file($file)) {
            return $file;
        }

        if (!class_exists(\Composer\Autoload\ClassLoader::class)) {
            return null;
        }
        try {
            $dir = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);
        } catch (\ReflectionException) {
            return null;
        }
        $file = $dir.'/composer/installed.php';

        return is_file($file) ? $file : null;
    }

    /**
     * Migawka do historii zmian: pakiety plus wersja PHP, bo podbicie PHP przez
     * hostingodawcę potrafi wywrócić sklep tak samo skutecznie jak aktualizacja
     * pakietu, a nigdzie indziej nie zostawia śladu.
     *
     * @param array<string, string> $packages
     *
     * @return array<string, string>
     */
    public static function withPlatform(array $packages, ?string $phpVersion = null): array
    {
        $packages['php'] = $phpVersion ?? \PHP_VERSION;
        ksort($packages);

        return $packages;
    }
}
