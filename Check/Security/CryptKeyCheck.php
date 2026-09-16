<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\DeploymentConfig;

/**
 * Klucz szyfrujący z app/etc/env.php. Magento szyfruje nim dane dostępu do
 * bramek płatniczych, tokeny integracji i ciasteczka, więc klucz domyślny,
 * skopiowany ze środowiska testowego albo za krótki znaczy, że te dane są
 * do odczytania przez każdego, kto zna wartość.
 *
 * Instalacje po rotacji trzymają w tym miejscu kilka kluczy naraz (stare są
 * potrzebne do odczytania starych danych) i to jest w porządku: sprawdzamy
 * ten, którym Magento szyfruje teraz, czyli ostatni.
 */
class CryptKeyCheck implements HealthCheckInterface
{
    private const LABEL = 'Klucz szyfrujący';
    // Wymiana klucza przeszyfrowuje dane w bazie: to operacja na żywym sklepie
    // i wymaga kopii zapasowej, dlatego mówimy o tym w podpowiedzi wprost.
    private const FIX = 'Wymień klucz poleceniem Magento: przeszyfruje ono zapisane dane płatności i integracji. Zrób wcześniej kopię bazy i pliku app/etc/env.php.';
    private const COMMAND = 'bin/magento encryption:key:change';

    private const MIN_LENGTH = 32;
    private const KNOWN_DEFAULTS = ['changeme', 'secret', 'magento', 'test', '0000000000000000000000000000000'];

    public function __construct(private readonly DeploymentConfig $deploymentConfig)
    {
    }

    public function run(): ?CheckResult
    {
        try {
            $raw = (string) $this->deploymentConfig->get('crypt/key', '');
        } catch (\Throwable) {
            return null;
        }

        $keys = array_values(array_filter(array_map('trim', explode("\n", $raw)), static fn (string $key): bool => '' !== $key));
        if ([] === $keys) {
            return CheckResult::fail('crypt_key', self::LABEL, 'W app/etc/env.php nie ma klucza szyfrującego. Dane dostępu do płatności i tokeny integracji nie są niczym chronione.',
                fix: self::FIX, command: self::COMMAND);
        }

        $current = end($keys);
        $rotated = \count($keys) > 1 ? sprintf(' Kluczy w pliku jest %d, co znaczy, że były rotowane: to dobrze.', \count($keys)) : '';

        if (\in_array(mb_strtolower($current), self::KNOWN_DEFAULTS, true)) {
            return CheckResult::fail('crypt_key', self::LABEL, 'Klucz szyfrujący ma wartość z przykładów. Każdy zna ją z dokumentacji, więc zaszyfrowane dane płatności są jawne. Wymień go poleceniem bin/magento encryption:key:change.',
                fix: self::FIX, command: self::COMMAND);
        }
        if (mb_strlen($current) < self::MIN_LENGTH) {
            return CheckResult::warn('crypt_key', self::LABEL, sprintf(
                'Klucz szyfrujący ma %d znaków, zalecane minimum to %d. Wymień go poleceniem bin/magento encryption:key:change.',
                mb_strlen($current),
                self::MIN_LENGTH
            ), fix: self::FIX, command: self::COMMAND);
        }

        return CheckResult::ok('crypt_key', self::LABEL, 'Klucz szyfrujący jest własny i odpowiednio długi.'.$rotated);
    }
}
