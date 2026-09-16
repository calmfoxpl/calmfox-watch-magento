<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\InstalledPackages;
use Calmfox\Watch\Model\Paths;
use Magento\Framework\App\State;

/**
 * Pakiety deweloperskie na produkcji. Same w sobie nie wystawiają sklepu,
 * ale to martwy kod z własnymi podatnościami, a narzędzia testowe potrafią
 * dokładać do tego własne punkty wejścia. Wdrożenie robi się poleceniem
 * `composer install --no-dev --optimize-autoloader`.
 *
 * Poza trybem produkcyjnym nie zawracamy tym głowy: na maszynie deweloperskiej
 * te pakiety mają być.
 */
class DevPackagesCheck implements HealthCheckInterface
{
    private const LABEL = 'Pakiety deweloperskie';
    private const RISKY = [
        'phpunit/phpunit',
        'magento/magento2-functional-testing-framework',
        'magento/magento-coding-standard',
        'friendsofphp/php-cs-fixer',
        'allure-framework/allure-phpunit',
        'symfony/var-dumper',
    ];

    public function __construct(
        private readonly Paths $paths,
        private readonly State $state,
    ) {
    }

    public function run(): ?CheckResult
    {
        $installed = InstalledPackages::load($this->paths->vendor());
        if ([] === $installed) {
            return CheckResult::warn('dev_packages', self::LABEL, 'Nie znaleźliśmy spisu zainstalowanych pakietów (vendor/composer/installed.php), więc tego nie sprawdzamy.');
        }

        $found = array_values(array_intersect(self::RISKY, array_keys($installed)));
        if ([] === $found) {
            return CheckResult::ok('dev_packages', self::LABEL, 'Brak pakietów deweloperskich w wydaniu.');
        }

        try {
            $mode = $this->state->getMode();
        } catch (\Throwable) {
            $mode = State::MODE_DEFAULT;
        }
        if (State::MODE_PRODUCTION !== $mode) {
            return CheckResult::ok('dev_packages', self::LABEL, sprintf('Zainstalowane pakiety deweloperskie (%d), ale sklep nie działa w trybie produkcyjnym.', \count($found)));
        }

        return CheckResult::warn('dev_packages', self::LABEL, sprintf(
            'W wydaniu produkcyjnym są pakiety deweloperskie: %s. Wdrażaj poleceniem composer install --no-dev.',
            implode(', ', \array_slice($found, 0, 4))
        ), fix: 'Na produkcji instaluj bez wymagań deweloperskich i po każdym wdrożeniu przeładuj autoloader. Pakiety dev zostają wtedy wyłącznie na maszynie programisty.',
            command: 'composer install --no-dev --optimize-autoloader');
    }
}
