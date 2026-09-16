<?php

declare(strict_types=1);

namespace Calmfox\Watch\Console\Command;

use Calmfox\Watch\Model\HubClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Świadome zakończenie monitoringu wnętrza. Mówimy hubowi wprost, zamiast
 * zostawiać mu milczący adres i budzić ludzi zdarzeniem o zablokowanym module.
 * To polecenie warto wpiąć w skrypt wdrożeniowy przed usunięciem modułu.
 */
class DisconnectCommand extends Command
{
    public function __construct(
        private readonly HubClient $hub,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('calmfox:watch:disconnect')
            ->setDescription('Kończy monitoring wnętrza sklepu i informuje o tym panel');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->hub->disconnect();

        if (!$result['ok']) {
            $io->warning(sprintf('Monitoring wstrzymany w sklepie, ale panel nie potwierdził rozłączenia: %s', $result['message']));

            return Command::FAILURE;
        }
        $io->success($result['message']);

        return Command::SUCCESS;
    }
}
