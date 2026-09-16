<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\FixCommand;
use Calmfox\Watch\Model\Paths;

/**
 * Katalogi zapisywalne dla wszystkich. 777 na pub/media to najkrótsza droga
 * od cudzego procesu na serwerze do własnego pliku PHP w sklepie, a na
 * generated/ dokłada jeszcze możliwość podmiany klas, które Magento ładuje
 * przy każdym żądaniu.
 */
class DirPermsCheck implements HealthCheckInterface
{
    private const LABEL = 'Uprawnienia katalogów';

    public function __construct(private readonly Paths $paths)
    {
    }

    public function run(): ?CheckResult
    {
        $world = [];
        $paths = [];
        foreach (['var/' => $this->paths->var(), 'pub/media' => $this->paths->media(), 'generated/' => $this->paths->generated()] as $name => $dir) {
            if ('' !== $dir && is_dir($dir) && (fileperms($dir) & 0002)) {
                $world[] = sprintf('%s (%o)', $name, fileperms($dir) & 0777);
                $paths[] = rtrim($name, '/');
            }
        }

        // -R świadomie: prawa 777 zwykle siedzą też w podkatalogach, a zmiana samego
        // katalogu nadrzędnego zostawiłaby otwarte dokładnie te miejsca, do których
        // trafiają wgrywane pliki.
        return CheckResult::of([] !== $world ? CheckResult::FAIL : CheckResult::OK, 'dir_perms', self::LABEL, [] !== $world
            ? sprintf('Zapisywalne dla wszystkich: %s. Dowolny proces na serwerze może umieścić tam własny plik.', implode(', ', $world))
            : 'Katalogi zapisywalne wyłącznie dla właściciela.',
            fix: 'Katalogom roboczym sklepu wystarczy 755, a gdy serwer WWW pracuje na innym użytkowniku niż właściciel plików, 775 przy wspólnej grupie.',
            command: FixCommand::chmod('755', $paths, recursive: true));
    }
}
