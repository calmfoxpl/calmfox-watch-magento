<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\ResponseSigner;
use PHPUnit\Framework\TestCase;

final class ResponseSignerTest extends TestCase
{
    private const SECRET = '0123456789abcdef0123456789abcdef';

    /**
     * Wartość policzona ręcznie z kontraktu (nonce, znacznik czasu i treść
     * sklejone znakiem nowej linii). Gdyby ktoś zmienił kolejność albo
     * separator, hub uznałby wszystkie odpowiedzi za podrobione.
     */
    public function testProofFollowsTheContractToTheByte(): void
    {
        $nonce = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
        $generatedAt = '2026-08-19T10:00:00+00:00';
        $body = '{"schema":1}';

        self::assertSame(
            hash_hmac('sha256', $nonce."\n".$generatedAt."\n".$body, self::SECRET),
            ResponseSigner::proof($nonce, $generatedAt, $body, self::SECRET)
        );
    }

    public function testAnyChangeOfTheBodyBreaksTheProof(): void
    {
        $proof = ResponseSigner::proof('nonce', '2026-08-19T10:00:00+00:00', '{"status":"ok"}', self::SECRET);

        self::assertNotSame($proof, ResponseSigner::proof('nonce', '2026-08-19T10:00:00+00:00', '{"status":"fail"}', self::SECRET));
        self::assertNotSame($proof, ResponseSigner::proof('inny', '2026-08-19T10:00:00+00:00', '{"status":"ok"}', self::SECRET));
        self::assertNotSame($proof, ResponseSigner::proof('nonce', '2026-08-19T10:05:00+00:00', '{"status":"ok"}', self::SECRET));
        self::assertNotSame($proof, ResponseSigner::proof('nonce', '2026-08-19T10:00:00+00:00', '{"status":"ok"}', 'cudzy-sekret'));
    }

    public function testGeneratedAtIsIso8601InUtc(): void
    {
        self::assertSame('2026-08-19T10:00:00+00:00', ResponseSigner::generatedAt(1787133600));
    }

    /**
     * Podpis liczymy nad bajtami, które naprawdę wychodzą na łącze, więc
     * kodowanie musi być jednoznaczne: bez ucieczek unicode i ukośników.
     */
    public function testEncodeKeepsPolishTextAndSlashesReadable(): void
    {
        $body = ResponseSigner::encode(['detail' => 'Zajętość dysku: https://sklep.pl/media']);

        self::assertSame('{"detail":"Zajętość dysku: https://sklep.pl/media"}', $body);
    }
}
