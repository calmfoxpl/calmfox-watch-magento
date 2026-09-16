<?php

declare(strict_types=1);

namespace Calmfox\Watch\Console\Command;

use Calmfox\Watch\Core\ResponseSigner;
use Calmfox\Watch\Model\PayloadProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Wypisuje payload lokalnie, bez pytania przez sieć i bez klucza. Przydaje się
 * przy diagnozowaniu różnicy między „check mówi fail" a „adres kontrolny nie
 * odpowiada": tutaj widać wyłącznie pierwszą z tych rzeczy.
 */
class HealthCommand extends Command
{
    public function __construct(
        private readonly PayloadProvider $payloads,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('calmfox:watch:health')
            ->setDescription('Wypisuje payload adresu kontrolnego bez wychodzenia przez sieć')
            ->addOption('section', null, InputOption::VALUE_REQUIRED, 'health albo security', PayloadProvider::SECTION_HEALTH);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $section = (string) $input->getOption('section');
        if (!\in_array($section, [PayloadProvider::SECTION_HEALTH, PayloadProvider::SECTION_SECURITY], true)) {
            $output->writeln('<error>Sekcja może być tylko „health" albo „security".</error>');

            return Command::INVALID;
        }

        $payload = $this->payloads->payload($section, true);
        $output->writeln(ResponseSigner::encode($payload, true));

        return 'fail' === ($payload['status'] ?? '') ? Command::FAILURE : Command::SUCCESS;
    }
}
