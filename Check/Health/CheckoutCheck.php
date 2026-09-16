<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Health;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Czy w tym sklepie da się w ogóle kupić. To jedyny check, który patrzy na
 * konfigurację sprzedaży, i powstał z konkretnej awarii: strona świeciła się
 * na zielono (baza, dysk, poczta), a od dwóch dni nikt nie mógł złożyć
 * zamówienia, bo wyłączona metoda płatności została wyłączona „na chwilę".
 *
 * Sprawdzamy warunki konieczne, nie biznes: każdy włączony widok sklepu musi
 * mieć co najmniej jedną włączoną metodę płatności i jedną włączoną metodę
 * dostawy. Metody „Zero Subtotal Checkout" (payment/free) świadomie NIE liczymy
 * jako płatności: obsługuje wyłącznie zamówienia za zero złotych, a jest
 * włączona w każdej domyślnej instalacji, więc liczona wprost dawałaby
 * zielone światło sklepowi, który nie przyjmie ani złotówki.
 *
 * W obroty, liczby zamówień i cokolwiek innego z danych sprzedażowych nie
 * wchodzimy i nie wysyłamy tego do huba.
 */
class CheckoutCheck implements HealthCheckInterface
{
    private const LABEL = 'Ścieżka zakupowa';
    /** Ile widoków sklepu sprawdzamy: przy kilkudziesięciu i tak liczy się pierwszy problem. */
    private const MAX_STORES = 20;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function run(): ?CheckResult
    {
        $start = microtime(true);
        try {
            $stores = $this->storeManager->getStores();
        } catch (\Throwable) {
            return null; // brak bazy ma zapalić check `db`, a nie dublować się tutaj
        }

        $active = [];
        foreach ($stores as $store) {
            if (method_exists($store, 'isActive') && !$store->isActive()) {
                continue;
            }
            $active[] = $store;
            if (\count($active) >= self::MAX_STORES) {
                break;
            }
        }

        if ([] === $active) {
            return CheckResult::fail('checkout', self::LABEL, 'Żaden widok sklepu nie jest włączony, więc sklep nie obsłuży ani jednego zamówienia.', self::ms($start));
        }

        $broken = [];
        foreach ($active as $store) {
            $code = (string) $store->getCode();
            $problems = $this->problemsFor($code);
            if ([] !== $problems) {
                $broken[] = sprintf('%s: %s', $code, implode(', ', $problems));
            }
        }

        $ms = self::ms($start);
        if ([] !== $broken) {
            return CheckResult::fail('checkout', self::LABEL, sprintf(
                'Klient nie dokończy zamówienia. %s',
                implode('; ', \array_slice($broken, 0, 3))
            ), $ms);
        }

        return CheckResult::ok('checkout', self::LABEL, sprintf(
            'Każdy z %d włączonych widoków sklepu ma czynną metodę płatności i dostawy.',
            \count($active)
        ), $ms);
    }

    /** @return list<string> */
    private function problemsFor(string $storeCode): array
    {
        $problems = [];

        $payments = $this->activeCodes('payment', $storeCode, ['free']);
        if ([] === $payments) {
            $problems[] = 'brak włączonej metody płatności (poza Zero Subtotal)';
        }

        $carriers = $this->activeCodes('carriers', $storeCode, []);
        if ([] === $carriers) {
            $problems[] = 'brak włączonej metody dostawy';
        }

        return $problems;
    }

    /**
     * @param list<string> $ignored
     *
     * @return list<string>
     */
    private function activeCodes(string $group, string $storeCode, array $ignored): array
    {
        try {
            $config = $this->scopeConfig->getValue($group, ScopeInterface::SCOPE_STORE, $storeCode);
        } catch (\Throwable) {
            return [];
        }
        if (!\is_array($config)) {
            return [];
        }

        $codes = [];
        foreach ($config as $code => $values) {
            if (!\is_array($values) || \in_array((string) $code, $ignored, true)) {
                continue;
            }
            if (\in_array((string) ($values['active'] ?? '0'), ['1', 'true'], true)) {
                $codes[] = (string) $code;
            }
        }

        return $codes;
    }

    private static function ms(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
