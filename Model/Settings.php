<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;

/**
 * Ustawienia modułu. Kolejność źródeł: zmienna środowiskowa, potem sekcja
 * `calmfox_watch` w app/etc/env.php, na końcu wartość domyślna. Świadomie NIE
 * ma ich w Stores → Configuration: adres API i katalog stanu muszą być znane
 * także wtedy, gdy baza nie odpowiada, a to jest właśnie moment, w którym
 * monitoring ma pracować.
 */
class Settings
{
    private const DEFAULT_API = 'https://watch.calmfox.net';

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function apiUrl(): string
    {
        $url = rtrim($this->value('CALMFOX_WATCH_API_URL', 'calmfox_watch/api_url', self::DEFAULT_API), '/');

        // Przeprowadzka panelu na watch.calmfox.net. Adres bywa zapisany w konfiguracji
        // sklepu z czasów parowania, a sama zmiana wartości domyślnej tego nie rusza.
        // Stary host oddaje 308, więc podmiana niczego nie naprawia — usuwa tylko skok
        // przy każdym żądaniu i adres, którego już nie używamy, z panelu administratora.
        return 'https://watch.calmfox.pl' === $url ? self::DEFAULT_API : $url;
    }

    public function stateDir(): ?string
    {
        $value = $this->value('CALMFOX_WATCH_STATE_DIR', 'calmfox_watch/state_dir', '');

        return '' !== $value ? $value : null;
    }

    public function composerBinary(): string
    {
        return $this->value('CALMFOX_WATCH_COMPOSER', 'calmfox_watch/composer_binary', 'composer');
    }

    /**
     * Adres sklepu. Szyfrowany ma pierwszeństwo, bo to on jest adresem
     * publicznym wszędzie tam, gdzie sklep ma certyfikat, a adres kontrolny
     * zgłaszany do huba musi być tym, pod którym monitoring naprawdę zapuka.
     */
    public function baseUrl(): string
    {
        foreach (['web/secure/base_url', 'web/unsecure/base_url'] as $path) {
            $value = trim((string) $this->scopeConfig->getValue($path));
            if ('' !== $value) {
                return rtrim($value, '/');
            }
        }

        return '';
    }

    private function value(string $env, string $configPath, string $default): string
    {
        $fromEnv = $_ENV[$env] ?? $_SERVER[$env] ?? getenv($env);
        if (\is_string($fromEnv) && '' !== trim($fromEnv)) {
            return trim($fromEnv);
        }

        try {
            $fromConfig = $this->deploymentConfig->get($configPath);
        } catch (\Throwable) {
            $fromConfig = null;
        }

        return \is_string($fromConfig) && '' !== trim($fromConfig) ? trim($fromConfig) : $default;
    }
}
