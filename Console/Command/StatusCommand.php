<?php

declare(strict_types=1);

namespace Calmfox\Watch\Console\Command;

use Calmfox\Watch\Core\SecretManager;
use Calmfox\Watch\Core\StateStore;
use Calmfox\Watch\Model\HubClient;
use Calmfox\Watch\Model\PayloadProvider;
use Calmfox\Watch\Model\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Stan połączenia i skrót obu sekcji. Pierwsze polecenie po wdrożeniu. */
class StatusCommand extends Command
{
    public function __construct(
        private readonly StateStore $state,
        private readonly SecretManager $secrets,
        private readonly HubClient $hub,
        private readonly PayloadProvider $payloads,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('calmfox:watch:status')
            ->setDescription('Stan połączenia z Calmfox Watch i skrót sprawdzeń');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Calmfox Watch '.Version::NUMBER);

        $lastPoll = $this->secrets->lastPollAt();
        $io->definitionList(
            ['Połączenie' => $this->state->get('connected', false) ? 'aktywne' : 'brak'],
            ['Adres kontrolny' => $this->hub->healthUrl() ?: 'nieznany (pusty adres bazowy sklepu)'],
            ['API' => $this->hub->apiUrl()],
            ['Pakiet' => (string) ($this->state->get('plan') ?: 'nieznany')],
            ['Katalog stanu' => $this->state->dir()],
            ['Ostatnie odpytanie' => null !== $lastPoll ? gmdate('Y-m-d H:i:s', $lastPoll).' UTC' : 'jeszcze nie było'],
        );

        $loopback = $this->hub->loopbackCheck();
        if (!$loopback['ok']) {
            $io->warning(sprintf('Sprawdzenie z serwera nie dociera do adresu kontrolnego: %s. Sprawdź reguły serwera WWW i zaporę dla ścieżki /calmfox-watch/.', $loopback['message']));
        }

        foreach ([PayloadProvider::SECTION_HEALTH => 'Stan usług', PayloadProvider::SECTION_SECURITY => 'Bezpieczeństwo'] as $section => $title) {
            $payload = $this->payloads->payload($section, true);
            $io->section(sprintf('%s: %s', $title, $payload['status']));
            $rows = [];
            foreach ($payload['checks'] as $check) {
                $rows[] = [$check['status'], $check['id'], $check['label'] ?? '', mb_substr((string) ($check['detail'] ?? ''), 0, 90)];
            }
            $io->table(['stan', 'identyfikator', 'nazwa', 'opis'], $rows);
        }

        return Command::SUCCESS;
    }
}
