<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\State;

/**
 * Tryb pracy aplikacji. Sklep w trybie deweloperskim na produkcji to nie jest
 * drobiazg: pokazuje pełne komunikaty błędów ze ścieżkami i zapytaniami,
 * kompiluje klasy i pliki statyczne przy każdym żądaniu i jest kilka razy
 * wolniejszy. Tryb domyślny (default) jest pośrodku: nie ujawnia tyle, ale
 * też generuje wszystko w locie, więc na produkcji nie ma czego szukać.
 */
class AppModeCheck implements HealthCheckInterface
{
    private const LABEL = 'Tryb pracy aplikacji';

    // Przełączenie trybu kompiluje kod i publikuje pliki statyczne, więc na dużym
    // sklepie potrafi trwać kilka minut i w tym czasie sklep bywa nieczynny.
    private const FIX = 'Przełącz sklep w tryb production. Zrób to poza godzinami szczytu: polecenie kompiluje kod i publikuje pliki statyczne, co na dużym katalogu trwa kilka minut.';
    private const COMMAND = 'bin/magento deploy:mode:set production';

    public function __construct(private readonly State $state)
    {
    }

    public function run(): ?CheckResult
    {
        try {
            $mode = $this->state->getMode();
        } catch (\Throwable) {
            return null;
        }

        return match ($mode) {
            State::MODE_PRODUCTION => CheckResult::ok('app_mode', self::LABEL, 'Tryb produkcyjny.'),
            State::MODE_DEVELOPER => CheckResult::fail('app_mode', self::LABEL, 'Sklep chodzi w trybie deweloperskim. Odwiedzający widzą pełne komunikaty błędów ze ścieżkami serwera, a każde żądanie kompiluje kod od nowa.',
                fix: self::FIX, command: self::COMMAND),
            default => CheckResult::warn('app_mode', self::LABEL, sprintf('Tryb „%s". Na produkcji powinien być tryb production: domyślny generuje pliki statyczne i klasy w locie, więc sklep jest wolniejszy i mniej przewidywalny.', $mode),
                fix: self::FIX, command: self::COMMAND),
        };
    }
}
