<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests;

use Calmfox\Watch\Core\ResponseSigner;
use Calmfox\Watch\Tests\Support\SamplePayloads;
use PHPUnit\Framework\TestCase;

/**
 * Próbki z docs/ mają być REALNYM wyjściem naszego buildera, a nie ręcznie
 * pisanym JSON-em: hub dostaje na nich test kontraktowy, więc rozjazd między
 * plikiem a kodem byłby rozjazdem między obietnicą a odpowiedzią.
 *
 * Regeneracja po świadomej zmianie kontraktu:
 *   CALMFOX_WRITE_SAMPLES=1 ../../api/vendor/bin/phpunit --filter SamplePayloads
 */
final class SamplePayloadsTest extends TestCase
{
    public function testHealthSampleMatchesTheBuilderOutput(): void
    {
        $this->assertSampleMatches('sample-health.json', SamplePayloads::health());
    }

    public function testSecuritySampleMatchesTheBuilderOutput(): void
    {
        $this->assertSampleMatches('sample-security.json', SamplePayloads::security());
    }

    public function testHealthSampleStaysInsideTheContract(): void
    {
        $payload = SamplePayloads::health();

        self::assertSame('warn', $payload['status'], 'Jeden check na warn i żaden na fail znaczy warn dla całej sekcji.');
        self::assertSame(
            ['db', 'disk', 'smtp', 'magento_cron', 'indexers', 'app_cache', 'checkout', 'search_engine', 'queue'],
            array_column($payload['checks'], 'id')
        );
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $payload['signals']['adminsFingerprint']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $payload['signals']['pluginsFingerprint']);
        self::assertSame(\count($payload['signals']['activePlugins']), $payload['signals']['pluginCount']);
        self::assertArrayNotHasKey('autoUpdates', $payload['signals'], 'Magento nie aktualizuje się samo, więc pole zostaje nieobecne zamiast kłamać wartością.');
    }

    public function testSecuritySampleStaysInsideTheContract(): void
    {
        $payload = SamplePayloads::security();

        self::assertSame('warn', $payload['status']);
        self::assertSame([
            'admin_count', 'admin_login', 'admin_path', 'two_factor', 'app_mode', 'debug_display', 'https',
            'php_version', 'config_perms', 'dir_perms', 'crypt_key', 'dev_packages', 'pending_updates',
        ], array_column($payload['checks'], 'id'));
        foreach ($payload['history'] as $entry) {
            self::assertContains($entry['kind'], ['core', 'plugin'], 'Na platformach composerowych „theme" nie występuje: motywy Magento są zwykłymi pakietami.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertSampleMatches(string $name, array $payload): void
    {
        $path = \dirname(__DIR__).'/docs/'.$name;
        $expected = ResponseSigner::encode($payload, true)."\n";

        if ('1' === (string) getenv('CALMFOX_WRITE_SAMPLES')) {
            file_put_contents($path, $expected);
        }

        self::assertFileExists($path);
        self::assertSame($expected, (string) file_get_contents($path), sprintf('Próbka %s rozjechała się z kodem. Regeneruj: CALMFOX_WRITE_SAMPLES=1 phpunit --filter SamplePayloads', $name));
    }
}
