<?php

/**
 * Autoloader PSR-4 bez Composera: testy rdzenia mają działać w każdym
 * środowisku, także tam, gdzie nie ma sieci na `composer install` ani
 * zainstalowanego Magento. Ładujemy wyłącznie własne przestrzenie nazw, więc
 * próba użycia klasy Magento w teście od razu się wywali i pilnuje granicy
 * „rdzeń niezależny od frameworka".
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $roots = [
        'Calmfox\\Watch\\Tests\\' => __DIR__.'/',
        'Calmfox\\Watch\\' => \dirname(__DIR__).'/',
    ];

    foreach ($roots as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $path = $dir.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';
        if (is_file($path)) {
            require_once $path;
        }

        return;
    }
});
