<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Model\AdminSignals;

/** Im mniej kont z dostępem do panelu, tym mniejsza powierzchnia ataku. */
class AdminCountCheck implements HealthCheckInterface
{
    public function __construct(private readonly AdminSignals $signals)
    {
    }

    public function run(): ?CheckResult
    {
        $snapshot = $this->signals->snapshot();
        if (!$snapshot['available']) {
            return null;
        }

        $count = $snapshot['count'];
        $many = $count > 5;

        return CheckResult::of($many ? CheckResult::WARN : CheckResult::OK, 'admin_count', 'Liczba administratorów', sprintf(
            'Aktywnych kont w panelu: %s.%s',
            $snapshot['truncated'] ? $count.'+' : (string) $count,
            $many ? ' Im mniej kont z dostępem do zamówień i cen, tym mniejsza powierzchnia ataku. Konta osób, które odeszły, warto wyłączyć.' : ''
        ), fix: 'W panelu (System → Permissions → All Users) wyłącz konta osób, które już nie pracują ze sklepem, a pozostałym przypisz rolę węższą niż Administrators.');
    }
}
