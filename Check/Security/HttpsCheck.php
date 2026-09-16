<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Szyfrowanie połączenia. Patrzymy na konfigurację sklepu, a nie na bieżące
 * żądanie: za pośrednikiem sieciowym bez ustawionych zaufanych adresów żądanie
 * wygląda na nieszyfrowane, a `fail` w tej sekcji trafia do raportu i mówi
 * klientowi, że jego sklep nie ma certyfikatu, choć ma.
 *
 * W Magento samo „secure base url" nie wystarcza: dopóki nie jest włączone
 * w sklepie i w panelu, kupujący i administratorzy chodzą po HTTP mimo
 * poprawnie ustawionego adresu.
 */
class HttpsCheck implements HealthCheckInterface
{
    private const LABEL = 'Szyfrowanie HTTPS';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function run(): ?CheckResult
    {
        $secureUrl = mb_strtolower(trim((string) $this->scopeConfig->getValue('web/secure/base_url')));
        if (!str_starts_with($secureUrl, 'https://')) {
            // Bez polecenia: podmiana adresu bazowego z literówką w domenie potrafi
            // odciąć dostęp do panelu, a naprawa idzie wtedy przez bazę.
            return CheckResult::fail('https', self::LABEL, 'Sklep nie ma ustawionego szyfrowanego adresu bazowego. Dane logowania i dane z formularzy przesyłane są otwartym tekstem, a przeglądarki ostrzegają kupujących.',
                fix: 'Włącz certyfikat u hostingodawcy i ustaw szyfrowany adres bazowy: Stores → Configuration → General → Web → Base URLs (Secure).');
        }

        $missing = [];
        if (!$this->scopeConfig->isSetFlag('web/secure/use_in_frontend')) {
            $missing[] = 'sklepie';
        }
        if (!$this->scopeConfig->isSetFlag('web/secure/use_in_adminhtml')) {
            $missing[] = 'panelu';
        }

        if ([] !== $missing) {
            return CheckResult::fail('https', self::LABEL, sprintf(
                'Adres szyfrowany jest ustawiony, ale HTTPS nie jest wymuszone w %s. Ruch idzie wtedy po HTTP mimo działającego certyfikatu.',
                implode(' i ', $missing)
            ), fix: 'W Stores → Configuration → General → Web → Base URLs (Secure) ustaw „Use Secure URLs” na Yes dla sklepu i dla panelu, a potem wyczyść pamięć podręczną.',
                command: 'bin/magento config:set web/secure/use_in_frontend 1');
        }

        return CheckResult::ok('https', self::LABEL, 'HTTPS wymuszone w sklepie i w panelu.');
    }
}
