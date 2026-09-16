<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Health;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\Indexer\ConfigInterface;
use Magento\Framework\Indexer\IndexerRegistry;

/**
 * Indeksy Magento. Unieważniony indeks nie wywraca sklepu od razu i właśnie
 * dlatego jest groźny: strona działa, a kupujący widzi wczorajszą cenę,
 * nieaktualny stan magazynowy albo produkt, którego już nie ma w kategorii.
 * Z zewnątrz nie widać tego wcale.
 *
 * Dlatego `warn`, nie `fail`: zamówienia dalej wchodzą, ale ktoś powinien
 * uruchomić przebudowę. Nazwy indeksów są uniwersalne dla całej platformy
 * (nie zdradzają, jakie moduły siedzą w sklepie), więc wolno je wysłać.
 */
class IndexersCheck implements HealthCheckInterface
{
    private const LABEL = 'Indeksy sklepu';

    public function __construct(
        private readonly ConfigInterface $indexerConfig,
        private readonly IndexerRegistry $indexerRegistry,
    ) {
    }

    public function run(): ?CheckResult
    {
        $start = microtime(true);
        try {
            $ids = array_keys($this->indexerConfig->getIndexers());
        } catch (\Throwable) {
            return null;
        }
        if ([] === $ids) {
            return null;
        }

        $invalid = [];
        $counted = 0;
        foreach ($ids as $id) {
            try {
                $indexer = $this->indexerRegistry->get((string) $id);
                ++$counted;
                if ($indexer->isInvalid()) {
                    $invalid[] = (string) $id;
                }
            } catch (\Throwable) {
                // Indeks zadeklarowany przez moduł, którego stan jest nie do odczytania:
                // nie zgadujemy, po prostu go nie liczymy.
                continue;
            }
        }

        $ms = (int) round((microtime(true) - $start) * 1000);
        if (0 === $counted) {
            return null;
        }
        if ([] === $invalid) {
            return CheckResult::ok('indexers', self::LABEL, sprintf('Wszystkie indeksy aktualne (%d).', $counted), $ms);
        }

        return CheckResult::warn('indexers', self::LABEL, sprintf(
            'Nieaktualne indeksy: %s. Kupujący mogą widzieć stare ceny, stany magazynowe albo niepełne kategorie. Przebuduj poleceniem bin/magento indexer:reindex.',
            implode(', ', \array_slice($invalid, 0, 6)).(\count($invalid) > 6 ? sprintf(' i %d dalszych', \count($invalid) - 6) : '')
        ), $ms);
    }
}
