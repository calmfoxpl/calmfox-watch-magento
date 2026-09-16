<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Health;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\DeploymentConfig;

/**
 * Pamięć podręczna aplikacji: zapis i odczyt klucza kontrolnego. Sam fakt, że
 * usługa odpowiada, nie wystarcza. Widzieliśmy Redisa, który przyjmował
 * połączenia i po cichu odrzucał zapisy (pełna pamięć), a sklep przy każdym
 * żądaniu budował konfigurację od zera. Na Magento kończy się to sekundami
 * na żądanie i awarią pod pierwszym większym ruchem.
 */
class AppCacheCheck implements HealthCheckInterface
{
    private const IDENTIFIER = 'calmfox_watch_ping';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly DeploymentConfig $deploymentConfig,
    ) {
    }

    public function run(): ?CheckResult
    {
        $label = 'Pamięć podręczna aplikacji';
        $backend = $this->backendName();
        if (null !== $backend) {
            $label .= ' ('.$backend.')';
        }

        $start = microtime(true);
        $expected = bin2hex(random_bytes(8));
        try {
            $this->cache->save($expected, self::IDENTIFIER, [], 60);
            $back = $this->cache->load(self::IDENTIFIER);
        } catch (\Throwable $e) {
            return CheckResult::fail('app_cache', $label, sprintf('Pamięć podręczna nie przyjmuje zapisu: %s', $e->getMessage()), self::ms($start));
        }

        $ms = self::ms($start);

        return $back === $expected
            ? CheckResult::ok('app_cache', $label, null, $ms)
            : CheckResult::fail('app_cache', $label, 'Zapis i odczyt nie zgadzają się. Usługa pamięci podręcznej mogła przestać działać, sklep będzie liczyć wszystko od nowa przy każdym wejściu.', $ms);
    }

    /** Nazwa silnika po ludzku, prosto z app/etc/env.php. */
    private function backendName(): ?string
    {
        try {
            $backend = (string) $this->deploymentConfig->get('cache/frontend/default/backend', '');
        } catch (\Throwable) {
            return null;
        }
        if ('' === $backend) {
            return 'plik';
        }
        foreach (['Redis' => 'Redis', 'Memcached' => 'Memcached', 'Apcu' => 'APCu', 'File' => 'plik', 'Database' => 'baza danych'] as $needle => $name) {
            if (str_contains($backend, $needle)) {
                return $name;
            }
        }

        return null;
    }

    private static function ms(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
