<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\MailConfig;
use PHPUnit\Framework\TestCase;

/**
 * Uczciwość checku poczty zaczyna się tutaj: rozpoznanie, czy w ogóle jest
 * z czym się połączyć. Zgadywanie kończyłoby się fałszywą awarią, a fałszywa
 * awaria budzi ludzi w nocy po nic.
 */
final class MailConfigTest extends TestCase
{
    public function testSmtpTransportWithHostIsTestable(): void
    {
        $config = MailConfig::parse(['transport' => 'smtp', 'host' => 'smtp.example.com', 'port' => '587', 'ssl' => 'tls']);

        self::assertSame(MailConfig::KIND_SMTP, $config['kind']);
        self::assertSame('smtp.example.com', $config['host']);
        self::assertSame(587, $config['port']);
        self::assertSame('tls', $config['secure']);
    }

    public function testPortFallsBackToTheOneMatchingEncryption(): void
    {
        self::assertSame(25, MailConfig::parse(['transport' => 'smtp', 'host' => 'mail.example.com'])['port']);
        self::assertSame(465, MailConfig::parse(['transport' => 'smtp', 'host' => 'mail.example.com', 'ssl' => 'ssl'])['port']);
        self::assertSame(587, MailConfig::parse(['transport' => 'smtp', 'host' => 'mail.example.com', 'ssl' => 'tls'])['port']);
        self::assertSame(587, MailConfig::parse(['transport' => 'smtp', 'host' => 'mail.example.com', 'ssl' => 'tls', 'port' => '0'])['port']);
    }

    /**
     * Starsze wydania Magento trzymały host i port w tej samej gałęzi, mimo że
     * wysyłały sendmailem. Host bez transportu „smtp" NIE jest więc dowodem,
     * że poczta idzie przez SMTP, i nie wolno na jego podstawie zapalić zieleni.
     */
    public function testHostWithoutSmtpTransportIsStillLocalDelivery(): void
    {
        $config = MailConfig::parse(['host' => 'localhost', 'port' => '25']);

        self::assertSame(MailConfig::KIND_LOCAL, $config['kind']);
        self::assertNull($config['host']);
        self::assertSame('sendmail', $config['transport']);
    }

    public function testSendmailIsLocalAndNamedAsSuch(): void
    {
        $config = MailConfig::parse(['transport' => 'sendmail']);

        self::assertSame(MailConfig::KIND_LOCAL, $config['kind']);
        self::assertSame('sendmail', $config['transport']);
    }

    public function testDisabledCommunicationsWinsOverEverythingElse(): void
    {
        foreach (['1', 1, true, 'true'] as $flag) {
            $config = MailConfig::parse(['disable' => $flag, 'transport' => 'smtp', 'host' => 'smtp.example.com']);
            self::assertSame(MailConfig::KIND_DISABLED, $config['kind'], 'Wyłączona wysyłka to najważniejsza informacja, reszta ustawień nie ma wtedy znaczenia.');
        }

        self::assertSame(MailConfig::KIND_LOCAL, MailConfig::parse(['disable' => '0'])['kind']);
    }

    public function testEmptyConfigurationIsLocalDeliveryNotAnError(): void
    {
        $config = MailConfig::parse([]);

        self::assertSame(MailConfig::KIND_LOCAL, $config['kind'], 'Sklep bez ustawień SMTP wysyła sendmailem, to nie jest awaria, tylko coś, czego nie zbadamy.');
    }
}
