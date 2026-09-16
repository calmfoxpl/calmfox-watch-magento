<?php

declare(strict_types=1);

namespace Calmfox\Watch\Console\Command;

use Calmfox\Watch\Core\StateStore;
use Calmfox\Watch\Core\VersionSnapshot;
use Calmfox\Watch\Model\PayloadProvider;
use Calmfox\Watch\Model\Paths;
use Calmfox\Watch\Model\Settings;
use Calmfox\Watch\Model\UpdateHistory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Liczy zaległe aktualizacje i zapisuje wynik do stanu. TO polecenie idzie
 * do crona systemowego (raz na dobę wystarczy): Composer potrzebuje sieci
 * i kilkudziesięciu sekund, więc w trakcie żądania HTTP nie ma o tym mowy.
 * Świadomie nie wpinamy go w zadania cykliczne Magento: proces sklepu nie
 * powinien wychodzić do repozytoriów pakietów, a awaria sieci nie ma prawa
 * zaśmiecać dziennika zadań.
 *
 * Dopóki nikt go nie uruchomi, pole `updates` w payloadzie NIE jest wysyłane.
 * Zero znaczyłoby „sprawdzone, nie ma czego aktualizować", a to byłaby nieprawda.
 *
 * Przy okazji uzgadniamy migawkę wersji, bo cron to najpewniejszy moment,
 * w którym wykryjemy wdrożenie zrobione poza sklepem.
 */
class UpdatesCommand extends Command
{
    private const TIMEOUT = 300;

    public function __construct(
        private readonly StateStore $state,
        private readonly UpdateHistory $history,
        private readonly PayloadProvider $payloads,
        private readonly Paths $paths,
        private readonly Settings $settings,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('calmfox:watch:updates')
            ->setDescription('Liczy zaległe aktualizacje pakietów i zapisuje wynik do stanu')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Licz także pakiety zależne, nie tylko wymagane wprost w composer.json');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->paths->root();

        $command = [$this->settings->composerBinary(), 'outdated', '--format=json', '--no-interaction', '--working-dir='.$projectDir];
        if (!$input->getOption('all')) {
            // Domyślnie tylko pakiety wymagane wprost: reszta i tak podniesie się
            // razem z nimi, a lista bez tego filtra jest nie do przeczytania
            // (sam metapakiet Magento ciągnie kilkaset zależności).
            $command[] = '--direct';
        }

        $process = new Process($command, $projectDir, null, null, self::TIMEOUT);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error(sprintf('Composer zakończył się błędem. %s', trim($process->getErrorOutput()) ?: trim($process->getOutput())));

            return Command::FAILURE;
        }

        $data = json_decode($process->getOutput(), true);
        if (!\is_array($data) || !\is_array($data['installed'] ?? null)) {
            $io->error('Nie rozumiem odpowiedzi Composera. Sprawdź wersję polecenia composer outdated.');

            return Command::FAILURE;
        }

        $core = 0;
        $packages = 0;
        foreach ($data['installed'] as $package) {
            $name = \is_array($package) ? (string) ($package['name'] ?? '') : '';
            if ('' === $name) {
                continue;
            }
            if (\in_array($name, VersionSnapshot::PLATFORM_PACKAGES, true)) {
                ++$core;

                continue;
            }
            ++$packages;
        }

        $this->state->set(['updates' => ['core' => $core, 'plugins' => $packages, 'themes' => 0, 'at' => gmdate('c')]]);
        $found = $this->history->reconcile(true);
        $this->payloads->forget();

        $io->success(sprintf('Zaległe aktualizacje: platforma %d, pozostałe pakiety %d.', $core, $packages));
        if ($found > 0) {
            $io->text(sprintf('Do historii dopisano %d zmian wersji wykrytych od ostatniego sprawdzenia.', $found));
        }

        return Command::SUCCESS;
    }
}
