<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\CheckNormalizer;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\PayloadBuilder;
use PHPUnit\Framework\TestCase;

final class PayloadBuilderTest extends TestCase
{
    public function testHealthPayloadMatchesTheContract(): void
    {
        $checks = CheckNormalizer::normalize([CheckResult::ok('db', 'Baza danych', null, 3), CheckResult::warn('indexers', 'Indeksy sklepu')]);
        $payload = PayloadBuilder::health('1.0.0', $checks, PayloadBuilder::site('2.4.7-p3', '8.3.14', '1.0.0'));

        self::assertSame(1, $payload['schema']);
        self::assertSame('1.0.0', $payload['plugin']);
        self::assertSame('warn', $payload['status'], 'Status sekcji liczy się z checków, nie podaje się go z zewnątrz.');
        self::assertSame(['db', 'indexers'], array_column($payload['checks'], 'id'));
        self::assertArrayNotHasKey('signals', $payload, 'Bez sygnałów pole w ogóle nie powstaje.');
    }

    /**
     * Zero w `updates` znaczy „sprawdzone, nie ma czego aktualizować". Dopóki
     * nikt nie policzył, pole musi zniknąć w całości, żeby panel powiedział
     * „brak danych" zamiast pokazać zielone zero wzięte z powietrza.
     */
    public function testUpdatesAreOmittedUntilSomebodyCountsThem(): void
    {
        $site = PayloadBuilder::site('2.4.7-p3', '8.3.14', '1.0.0');
        self::assertArrayNotHasKey('updates', $site);

        $counted = PayloadBuilder::site('2.4.7-p3', '8.3.14', '1.0.0', ['core' => 1, 'plugins' => 4]);
        self::assertSame(['core' => 1, 'plugins' => 4, 'themes' => 0], $counted['updates']);
    }

    public function testNegativeUpdateCountsAreClampedInsteadOfLeakingOut(): void
    {
        $site = PayloadBuilder::site('2.4.7-p3', '8.3.14', '1.0.0', ['core' => -3, 'plugins' => -1]);

        self::assertSame(['core' => 0, 'plugins' => 0, 'themes' => 0], $site['updates']);
    }

    public function testPlatformVersionTravelsInTheHistoricallyNamedField(): void
    {
        $site = PayloadBuilder::site('2.4.7-p3', '8.3.14', '1.0.0');

        self::assertSame('2.4.7-p3', $site['wp'], 'Pole nazywa się „wp" ze względów historycznych i niesie wersję platformy.');
        self::assertSame('8.3.14', $site['php']);
        self::assertSame('1.0.0', $site['plugin']);
    }

    public function testSecurityPayloadCarriesHistoryAndNotSiteData(): void
    {
        $payload = PayloadBuilder::security('1.0.0', CheckNormalizer::normalize([CheckResult::ok('https', 'HTTPS')]), [
            ['kind' => 'core', 'name' => 'magento/framework', 'from' => '103.0.6', 'to' => '103.0.7', 'at' => '2026-08-18T21:35:00+00:00', 'mode' => 'manual', 'by' => null],
        ]);

        self::assertSame('ok', $payload['status']);
        self::assertCount(1, $payload['history']);
        self::assertArrayNotHasKey('site', $payload, 'Wersje należą do sekcji health, historia do security.');
    }
}
