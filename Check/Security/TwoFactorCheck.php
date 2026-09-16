<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Uwierzytelnianie dwuskładnikowe w panelu. Magento dostarcza je z pudełka
 * od 2.4 i domyślnie wymaga, więc wyłączony moduł to zwykle ślad po „na chwilę,
 * bo przeszkadzał". Przy panelu, w którym leżą zamówienia, dane kupujących
 * i konfiguracja płatności, samo hasło jest jedynym, co dzieli atakującego
 * od sklepu, gdy wycieknie skądkolwiek indziej.
 */
class TwoFactorCheck implements HealthCheckInterface
{
    private const LABEL = 'Logowanie dwuskładnikowe (2FA)';
    /** Nazwy modułu w wydaniach społecznościowym i handlowym oraz w wersji z Adobe IMS. */
    private const MODULES = ['Magento_TwoFactorAuth', 'MSP_TwoFactorAuth', 'Magento_AdminAdobeIms'];

    public function __construct(
        private readonly ModuleListInterface $enabledModules,
        private readonly FullModuleList $allModules,
    ) {
    }

    public function run(): ?CheckResult
    {
        $installed = [];
        $enabled = [];
        foreach (self::MODULES as $module) {
            if ($this->allModules->has($module)) {
                $installed[] = $module;
            }
            if ($this->enabledModules->has($module)) {
                $enabled[] = $module;
            }
        }

        if ([] === $installed) {
            return CheckResult::warn('two_factor', self::LABEL, 'Nie znaleźliśmy modułu logowania dwuskładnikowego, więc nie mamy czego sprawdzić. W Magento 2.4 jest częścią platformy: jego brak znaczy albo starsze wydanie, albo świadome usunięcie.');
        }
        if ([] === $enabled) {
            return CheckResult::fail('two_factor', self::LABEL, 'Moduł logowania dwuskładnikowego jest zainstalowany, ale wyłączony. Do panelu ze wszystkimi zamówieniami wystarczy wtedy samo hasło.',
                fix: 'Włącz moduł i przebuduj konfigurację (setup:upgrade). Przy pierwszym logowaniu każdy administrator zostanie poproszony o sparowanie aplikacji z kodami.',
                command: 'bin/magento module:enable Magento_TwoFactorAuth');
        }

        return CheckResult::ok('two_factor', self::LABEL, 'Logowanie dwuskładnikowe do panelu jest włączone.');
    }
}
