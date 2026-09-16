<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\FixCommand;
use Calmfox\Watch\Model\Paths;

/**
 * Prawa do plików z danymi dostępu. Na hostingach współdzielonych 644 to norma,
 * więc alarmujemy dopiero przy prawie ZAPISU dla grupy albo dla wszystkich:
 * kto może dopisać do app/etc/env.php, ten podmienia dane bazy, klucz
 * szyfrujący i adres panelu, czyli przejmuje sklep bez logowania.
 */
class ConfigPermsCheck implements HealthCheckInterface
{
    private const LABEL = 'Uprawnienia plików konfiguracji';
    private const FILES = ['env.php', 'config.php'];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function run(): ?CheckResult
    {
        $world = [];
        $group = [];
        $offenders = [];
        $checked = 0;

        foreach (self::FILES as $name) {
            $path = rtrim($this->paths->config(), '/').'/'.$name;
            if (!is_file($path)) {
                continue;
            }
            ++$checked;
            $perms = fileperms($path) & 0777;
            if ($perms & 0002) {
                $world[] = sprintf('app/etc/%s (%o)', $name, $perms);
                $offenders[] = 'app/etc/'.$name;
            } elseif ($perms & 0020) {
                $group[] = sprintf('app/etc/%s (%o)', $name, $perms);
                $offenders[] = 'app/etc/'.$name;
            }
        }

        if (0 === $checked) {
            return CheckResult::warn('config_perms', self::LABEL, 'Nie znaleźliśmy plików app/etc/env.php ani app/etc/config.php, więc nie sprawdzamy ich uprawnień.');
        }
        $fix = 'Docelowe prawa to 640: właściciel czyta i zapisuje, grupa serwera WWW tylko czyta, reszta serwera nic. W env.php siedzą dane bazy i klucz szyfrujący płatności.';
        if ([] !== $world) {
            return CheckResult::fail('config_perms', self::LABEL, sprintf(
                'Zapisywalne dla wszystkich użytkowników serwera: %s. Ustaw 640 albo 600.',
                implode(', ', $world)
            ), fix: $fix, command: FixCommand::chmod('640', $offenders));
        }
        if ([] !== $group) {
            return CheckResult::warn('config_perms', self::LABEL, sprintf(
                'Zapisywalne dla grupy: %s. Na współdzielonym hostingu warto zejść do 600.',
                implode(', ', $group)
            ), fix: $fix, command: FixCommand::chmod('640', $offenders));
        }

        return CheckResult::ok('config_perms', self::LABEL, sprintf('Sprawdzone pliki (%d) nie są zapisywalne dla innych.', $checked));
    }
}
