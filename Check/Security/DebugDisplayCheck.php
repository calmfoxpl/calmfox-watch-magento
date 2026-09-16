<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Model\Paths;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Wyciek szczegółów działania sklepu. Trzy rzeczy naraz, bo każda z nich
 * kończy się tym samym: odwiedzający poznaje ścieżki plików, strukturę bazy
 * albo nazwy szablonów.
 *
 * pub/errors/local.xml jest tu najczęstszym grzechem: wystarczy skopiować
 * local.xml.sample przy szukaniu przyczyny awarii, żeby od tej pory każdy
 * wyjątek pokazywał odwiedzającym pełny ślad stosu. Plik zostaje na serwerze
 * miesiącami, bo nic o nim nie przypomina.
 */
class DebugDisplayCheck implements HealthCheckInterface
{
    private const LABEL = 'Ujawnianie szczegółów błędów';

    public function __construct(
        private readonly Paths $paths,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function run(): ?CheckResult
    {
        $problems = [];

        $displayErrors = (string) ini_get('display_errors');
        $errorsShown = '' !== $displayErrors && !\in_array(mb_strtolower($displayErrors), ['0', 'off'], true);
        if ($errorsShown) {
            $problems[] = 'PHP wyświetla błędy odwiedzającym (display_errors)';
        }

        $errorsLocal = rtrim($this->paths->pub(), '/').'/errors/local.xml';
        $localXml = is_file($errorsLocal);
        if ($localXml) {
            $problems[] = 'pub/errors/local.xml pokazuje pełne wyjątki zamiast strony błędu';
        }

        $hints = $this->scopeConfig->isSetFlag('dev/debug/template_hints_storefront');
        if ($hints) {
            $problems[] = 'włączone podpowiedzi szablonów na sklepie';
        }

        $fixes = [];
        if ($errorsShown) {
            $fixes[] = 'display_errors ustaw na Off w php.ini (błędy mają iść do logu, nie na stronę).';
        }
        if ($localXml) {
            $fixes[] = 'Skasuj pub/errors/local.xml, wzorzec zostaje w local.xml.sample.';
        }
        if ($hints) {
            $fixes[] = 'Wyłącz podpowiedzi szablonów: Stores → Configuration → Advanced → Developer.';
        }

        return CheckResult::of([] !== $problems ? CheckResult::FAIL : CheckResult::OK, 'debug_display', self::LABEL, [] !== $problems
            ? sprintf('%s. Każdy odwiedzający może poznać ścieżki plików, strukturę bazy i treść zapytań.', ucfirst(implode(', ', $problems)))
            : 'Błędy nie są wyświetlane odwiedzającym.',
            fix: implode(' ', $fixes),
            // Przy błędach PHP najpierw trzeba wiedzieć, KTÓRY php.ini jest wczytany
            // (hostingi mają ich po kilka); podpowiedzi szablonów wyłącza konfiguracja.
            command: $errorsShown ? 'php --ini'
                : ($hints ? 'bin/magento config:set dev/debug/template_hints_storefront 0' : null));
    }
}
