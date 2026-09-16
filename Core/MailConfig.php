<?php

declare(strict_types=1);

namespace Calmfox\Watch\Core;

/**
 * Rozbiór ustawień poczty Magento na tyle, ile potrzeba do UCZCIWEGO checku.
 * Zasada: testujemy tylko to, co da się przetestować.
 *
 * Magento (od 2.4.6) ma własne ustawienia transportu w `system/smtp`: `transport`
 * o wartości `sendmail` albo `smtp`, a przy `smtp` host, port i szyfrowanie.
 * Starsze wydania trzymały tam host i port, choć wysyłały sendmailem, więc host
 * bez transportu `smtp` NIE jest dowodem, że poczta idzie przez SMTP: w takim
 * układzie mówimy o wysyłce lokalnej i nie udajemy, że coś sprawdziliśmy.
 *
 * Osobna pułapka to moduły SMTP innych producentów. Trzymają konfigurację
 * u siebie, więc ich stąd nie widzimy i mówimy o tym wprost, zamiast zgłaszać
 * fałszywą awarię albo fałszywy spokój.
 */
final class MailConfig
{
    /** Serwer SMTP z jawnym hostem: można nawiązać połączenie i zmierzyć czas. */
    public const KIND_SMTP = 'smtp';
    /** Wysyłka lokalna (sendmail, ustawienia php.ini): nie ma z czym się łączyć. */
    public const KIND_LOCAL = 'local';
    /** Wysyłka wyłączona globalnie (system/smtp/disable). */
    public const KIND_DISABLED = 'disabled';

    private const DEFAULT_PORT = 25;
    private const DEFAULT_SSL_PORT = 465;
    private const DEFAULT_TLS_PORT = 587;

    /**
     * @param array<string, mixed> $smtp zawartość gałęzi konfiguracji `system/smtp`
     *
     * @return array{kind: string, host: ?string, port: ?int, secure: string, transport: string}
     */
    public static function parse(array $smtp): array
    {
        $disabled = self::flag($smtp['disable'] ?? null);
        if ($disabled) {
            return self::result(self::KIND_DISABLED, null, null, '', 'disabled');
        }

        $transport = mb_strtolower(trim((string) ($smtp['transport'] ?? '')));
        $host = trim((string) ($smtp['host'] ?? ''));
        $secure = mb_strtolower(trim((string) ($smtp['ssl'] ?? '')));
        $secure = \in_array($secure, ['ssl', 'tls'], true) ? $secure : '';

        if ('smtp' !== $transport || '' === $host) {
            // Sendmail, brak transportu albo host bez transportu SMTP: wysyłka lokalna.
            return self::result(self::KIND_LOCAL, null, null, '', '' !== $transport ? $transport : 'sendmail');
        }

        $port = (int) ($smtp['port'] ?? 0);
        if ($port <= 0 || $port > 65535) {
            $port = match ($secure) {
                'ssl' => self::DEFAULT_SSL_PORT,
                'tls' => self::DEFAULT_TLS_PORT,
                default => self::DEFAULT_PORT,
            };
        }

        return self::result(self::KIND_SMTP, $host, $port, $secure, 'smtp');
    }

    private static function flag(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    /** @return array{kind: string, host: ?string, port: ?int, secure: string, transport: string} */
    private static function result(string $kind, ?string $host, ?int $port, string $secure, string $transport): array
    {
        return ['kind' => $kind, 'host' => $host, 'port' => $port, 'secure' => $secure, 'transport' => $transport];
    }
}
