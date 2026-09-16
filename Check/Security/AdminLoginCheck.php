<?php

declare(strict_types=1);

namespace Calmfox\Watch\Check\Security;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;
use Magento\Framework\App\ResourceConnection;

/**
 * Konta o oczywistym loginie. Pierwszy cel ataków słownikowych, a przy okazji
 * najczęstsza pozostałość po instalacji z danymi przykładowymi na serwerze,
 * który potem został produkcją.
 */
class AdminLoginCheck implements HealthCheckInterface
{
    private const USERNAMES = ['admin', 'administrator', 'magento', 'test', 'demo'];
    private const EMAILS = ['admin@example.com', 'admin@admin.com', 'admin@localhost', 'test@example.com'];

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function run(): ?CheckResult
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('admin_user');
            if (!$connection->isTableExists($table)) {
                return null;
            }
            $found = $connection->fetchCol(
                $connection->select()->from($table, ['username'])
                    ->where('username IN (?)', self::USERNAMES)
                    ->orWhere('email IN (?)', self::EMAILS)
                    ->limit(10)
            );
        } catch (\Throwable) {
            return null;
        }

        $found = array_values(array_unique(array_map('strval', $found)));

        return CheckResult::of([] !== $found ? CheckResult::WARN : CheckResult::OK, 'admin_login', 'Konta o domyślnym loginie', [] !== $found
            ? sprintf('Istnieją konta o łatwych do odgadnięcia danych: %s. Zmień login albo wyłącz konto, jeżeli zostało po instalacji.', implode(', ', $found))
            : 'Brak kont o domyślnych loginach.',
            // Polecenia świadomie nie ma: wyłączenie konta administracyjnego zanim
            // zastępcze naprawdę działa to prosta droga do zamknięcia się na zewnątrz.
            fix: 'Załóż konto z własnym loginem, sprawdź logowanie na nie i dopiero wtedy wyłącz konto o domyślnej nazwie. Konta po instalacji przykładowej po prostu usuń.');
    }
}
