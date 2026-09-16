<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check;

use Calmfox\Watch\Core\CheckResult;

/**
 * Punkt rozszerzenia dla usług, o których wie tylko właściciel sklepu:
 * własny broker kolejek, integracja z magazynem, demon synchronizacji cen,
 * mostek do systemu księgowego. Implementację dopina się do sekcji `health`
 * przez `etc/di.xml` projektu, dokładając wpis do tablicy `checks` w typie
 * wirtualnym `Calmfox\Watch\Model\HealthRunner` (przykład w README).
 *
 * Dwie zasady, obie wynikają z kontraktu:
 * 1. Zwróć `null`, gdy check nie dotyczy tej instalacji. Nie wysyłamy „ok"
 *    o czymś, czego nie ma.
 * 2. Trzymaj się krótkiego, twardego limitu czasu. Adres kontrolny odpowiada
 *    monitoringowi co minutę i nie może zamulić sklepu.
 */
interface HealthCheckInterface
{
    public function run(): ?CheckResult;
}
