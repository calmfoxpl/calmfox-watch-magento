<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;

/** Stan wsparcia PHP na sierpień 2026: poniżej 8.2 bez wsparcia, 8.2 tylko łatki bezpieczeństwa. */
class PhpVersionCheck implements HealthCheckInterface
{
    public function __construct(private readonly string $version = \PHP_VERSION)
    {
    }

    public function run(): ?CheckResult
    {
        // Wersję PHP przestawia się w panelu hostingu, nie poleceniem: `php -v` w SSH
        // pokazuje wersję konsolową, która bywa inna niż ta, na której chodzi sklep.
        $fix = 'W panelu hostingu przestaw wersję PHP dla tej domeny na 8.3 lub nowszą (zgodną z Twoim wydaniem Magento), a po zmianie sprawdź sklep i panel.';
        if (version_compare($this->version, '8.2', '<')) {
            return CheckResult::fail('php_version', 'Wersja PHP', sprintf(
                'PHP %s nie dostaje już nawet poprawek bezpieczeństwa. Konieczna aktualizacja u hostingodawcy.',
                $this->version
            ), fix: $fix);
        }
        if (version_compare($this->version, '8.3', '<')) {
            return CheckResult::warn('php_version', 'Wersja PHP', sprintf(
                'PHP %s dostaje już tylko poprawki bezpieczeństwa. Zaplanuj przejście wyżej.',
                $this->version
            ), fix: $fix);
        }

        return CheckResult::ok('php_version', 'Wersja PHP', sprintf('PHP %s, wersja wspierana.', $this->version));
    }
}
