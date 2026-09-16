<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check;

use Calmfox\Watch\Core\CheckNormalizer;
use Psr\Log\LoggerInterface;

/**
 * Uruchamia komplet checków jednej sekcji. Wyjątek z pojedynczego checku nie
 * może wywrócić całej odpowiedzi: monitoring wolałby usłyszeć o dziewięciu
 * usługach niż o żadnej. Nasze checki łapią błędy u siebie i zamieniają je
 * na status `fail`, więc tutaj cicho pomijamy tylko to, co rozsypało się
 * w rozszerzeniu klienta (z wpisem do dziennika zdarzeń).
 */
class CheckRunner
{
    /** @param array<string, HealthCheckInterface> $checks */
    public function __construct(
        private readonly array $checks = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /** @return array{status: string, checks: list<array<string, mixed>>} */
    public function run(): array
    {
        $results = [];
        foreach ($this->checks as $name => $check) {
            if (!$check instanceof HealthCheckInterface) {
                continue;
            }
            try {
                $result = $check->run();
            } catch (\Throwable $e) {
                $this->logger?->warning('Check Calmfox Watch zakończył się wyjątkiem: {check} {error}', [
                    'check' => \is_string($name) ? $name : $check::class,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
            if (null !== $result) {
                $results[] = $result;
            }
        }

        $normalized = CheckNormalizer::normalize($results);

        return ['status' => CheckNormalizer::aggregate($normalized), 'checks' => $normalized];
    }
}
