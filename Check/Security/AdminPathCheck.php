<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;

/**
 * Adres panelu i klucz w adresach panelu. To nie jest „bezpieczeństwo przez
 * ukrycie dla samego ukrycia": panel pod domyślnym /admin dostaje ruch
 * z automatów praktycznie od pierwszego dnia, a każde takie żądanie to próba
 * logowania i obciążenie sklepu. Zmiana adresu odcina cały ten ruch, zanim
 * dotrze do formularza logowania.
 *
 * Drugi warunek to „Add Secret Key to URLs" (admin/security/use_form_key):
 * bez niego adresy panelu są przewidywalne, więc wystarczy podrzucić
 * zalogowanemu administratorowi odpowiedni odnośnik, żeby wykonał operację,
 * której nie zamierzał.
 */
class AdminPathCheck implements HealthCheckInterface
{
    private const LABEL = 'Adres panelu administracyjnego';
    private const DEFAULT_PATHS = ['admin', 'backend', 'administrator'];

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function run(): ?CheckResult
    {
        $problems = [];

        $frontName = mb_strtolower(trim((string) $this->deploymentConfig->get('backend/frontName', '')));
        $customPath = $this->scopeConfig->isSetFlag('admin/url/use_custom_path');
        if ('' !== $frontName && \in_array($frontName, self::DEFAULT_PATHS, true) && !$customPath) {
            $problems[] = sprintf('panel stoi pod przewidywalnym adresem /%s', $frontName);
        }

        if (!$this->scopeConfig->isSetFlag('admin/security/use_form_key')) {
            $problems[] = 'wyłączony klucz w adresach panelu (Add Secret Key to URLs)';
        }

        if ([] === $problems) {
            return CheckResult::ok('admin_path', self::LABEL, 'Panel ma własny adres, a jego odnośniki są zabezpieczone kluczem.');
        }

        return CheckResult::warn('admin_path', self::LABEL, sprintf(
            '%s. Automaty pukają do domyślnych adresów panelu nieprzerwanie, więc to najtańsza zmiana, jaką da się tu zrobić.',
            ucfirst(implode(', ', $problems))
        ), fix: 'Ustaw własny adres panelu (nie „admin”) i włącz klucz w adresach: Stores → Configuration → Advanced → Admin → Security → Add Secret Key to URLs. Po zmianie adresu zapisz nowy link.',
            command: 'bin/magento setup:config:set --backend-frontname=<wlasny-adres>');
    }
}
