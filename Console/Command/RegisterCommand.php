<?php

declare(strict_types=1);

namespace Calmfox\Watch\Console\Command;

use Calmfox\Watch\Model\HubClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Aktywacja pakietu Free bez wchodzenia do panelu. Na tej platformie wdrożenia
 * bywają w pełni skryptowe, więc każda operacja z ekranu ma odpowiednik w CLI.
 */
class RegisterCommand extends Command
{
    public function __construct(
        private readonly HubClient $hub,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('calmfox:watch:register')
            ->setDescription('Aktywuje pakiet Free i łączy sklep z Calmfox Watch')
            ->addArgument('email', InputArgument::REQUIRED, 'Adres e-mail właściciela sklepu');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $io->error('Podaj poprawny adres e-mail.');

            return Command::INVALID;
        }

        $io->text(sprintf('Adres kontrolny zgłaszany do Calmfox Watch: %s', $this->hub->healthUrl()));
        $result = $this->hub->register($email);

        if (!$result['ok']) {
            $io->error($result['message']);

            return Command::FAILURE;
        }
        $io->success($result['message']);

        return Command::SUCCESS;
    }
}
