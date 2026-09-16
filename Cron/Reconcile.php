<?php

declare(strict_types=1);

namespace Calmfox\Watch\Cron;

use Calmfox\Watch\Model\PayloadProvider;
use Calmfox\Watch\Model\UpdateHistory;
use Psr\Log\LoggerInterface;

/**
 * Nocne uzgodnienie migawki wersji pakietów. Sekcja `security` robi to sama
 * przy odpytaniu, ale tylko wtedy, gdy hub o nią pyta: przy sklepie odpiętym
 * na kilka dni wdrożenie przeszłoby niezauważone i historia zaczęłaby się
 * od stanu po zmianie, zamiast ją pokazać.
 *
 * Świadomie NIE liczymy tu zaległych aktualizacji: to wymaga Composera
 * i wyjścia do sieci, a proces sklepu nie ma po co tam chodzić. Od tego jest
 * calmfox:watch:updates w cronie systemowym.
 */
class Reconcile
{
    public function __construct(
        private readonly UpdateHistory $history,
        private readonly PayloadProvider $payloads,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        try {
            $found = $this->history->reconcile(true);
        } catch (\Throwable $e) {
            $this->logger->warning('Calmfox Watch: nie udało się uzgodnić migawki wersji. {error}', ['error' => $e->getMessage()]);

            return;
        }

        if ($found > 0) {
            $this->payloads->forget();
            $this->logger->info('Calmfox Watch: wykryto {count} zmian wersji pakietów.', ['count' => $found]);
        }
    }
}
