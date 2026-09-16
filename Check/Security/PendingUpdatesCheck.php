<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\StateStore;

/**
 * Zaległe aktualizacje. Composer nie odpowie na to pytanie w trakcie żądania
 * HTTP (potrzebuje sieci i sporo czasu), więc liczby bierzemy z pamięci stanu
 * zapisanej przez polecenie calmfox:watch:updates uruchamiane z crona.
 *
 * Gdy nikt nigdy nie policzył, mówimy o tym wprost i pole `updates`
 * w payloadzie POMIJAMY. Zero znaczyłoby „sprawdzone, nie ma czego
 * aktualizować", a to nieprawda i panel pokazałby zielony wynik wzięty
 * z powietrza.
 */
class PendingUpdatesCheck implements HealthCheckInterface
{
    public const STATE_KEY = 'updates';

    private const LABEL = 'Zaległe aktualizacje';
    /** Po tylu dniach bez przeliczenia liczby przestają być wiarygodne. */
    private const STALE_AFTER_DAYS = 14;

    public function __construct(private readonly StateStore $state)
    {
    }

    public function run(): ?CheckResult
    {
        $stored = $this->state->get(self::STATE_KEY);
        if (!\is_array($stored) || !isset($stored['plugins'])) {
            return CheckResult::warn('pending_updates', self::LABEL,
                'Nie sprawdzamy zaległych aktualizacji: nikt jeszcze nie uruchomił polecenia calmfox:watch:updates. Dopisz je do crona (raz na dobę wystarczy), a zaczniemy je liczyć.',
                fix: 'Uruchom polecenie raz ręcznie, a potem dopisz je do crona raz na dobę (wpis: 0 4 * * * cd /sciezka/do/sklepu && bin/magento calmfox:watch:updates).',
                command: 'bin/magento calmfox:watch:updates');
        }

        $core = (int) ($stored['core'] ?? 0);
        $packages = (int) ($stored['plugins'] ?? 0);
        $at = \is_string($stored['at'] ?? null) ? $stored['at'] : null;

        if (null !== $at && strtotime($at) < time() - self::STALE_AFTER_DAYS * 86400) {
            return CheckResult::warn('pending_updates', self::LABEL, sprintf(
                'Ostatnie przeliczenie %s. Liczby są nieaktualne, sprawdź, czy polecenie calmfox:watch:updates dalej chodzi w cronie.',
                substr($at, 0, 10)
            ), fix: 'Sprawdź wpis w cronie i uruchom polecenie ręcznie: jeśli przejdzie z konsoli, problem jest w harmonogramie, a nie w samym poleceniu.',
                command: 'bin/magento calmfox:watch:updates');
        }

        if ($core > 0) {
            return CheckResult::warn('pending_updates', self::LABEL, 'Dostępna aktualizacja Magento. Aktualizacja platformy jest najpilniejsza, bo to ona dostaje poprawki bezpieczeństwa (Adobe wydaje je w cyklu kwartalnym).',
                fix: 'Zacznij od sprawdzenia, co ma nowsze wersje, i aktualizuj najpierw platformę, na kopii sklepu, nie od razu na produkcji.',
                command: 'composer outdated --direct');
        }

        return CheckResult::of($packages >= 10 ? CheckResult::WARN : CheckResult::OK, 'pending_updates', self::LABEL, sprintf(
            'Pakietów do aktualizacji: %d.%s',
            $packages,
            $packages >= 10 ? ' Im dłużej odkładana aktualizacja, tym trudniejsza i ryzykowniejsza.' : ''
        ), fix: 'Przejrzyj listę zaległych pakietów i zaplanuj aktualizację partiami, zaczynając od tych, które dotykają płatności i bezpieczeństwa.',
            command: 'composer outdated --direct');
    }
}
