<?php

declare(strict_types=1);

namespace Calmfox\Watch\Tests\Support;

use Calmfox\Watch\Core\AdminFingerprint;
use Calmfox\Watch\Core\CheckNormalizer;
use Calmfox\Watch\Core\CheckResult;
use Calmfox\Watch\Core\PayloadBuilder;

/**
 * Próbki payloadu do docs/. Wyniki checków są ustalone (nie mamy tu bazy ani
 * sklepu), ale SKŁADANIE odpowiedzi robi ten sam kod, który odpowiada hubowi:
 * normalizacja, agregacja statusu, odcisk kont i budowa pól. Dzięki temu
 * próbka nie może rozjechać się z kontraktem bez zapalenia testu, a hub czyta
 * te pliki w swoim teście kontraktowym (api/tests/Plugin/PluginPayloadContractTest.php).
 */
final class SamplePayloads
{
    public const VERSION = '1.0.0';
    private const SECRET = '0123456789abcdef0123456789abcdef';

    /**
     * Skrócona lista włączonych modułów. W próbce nie ma sensu wypisywać pełnych
     * kilkuset, bo pokazujemy KSZTAŁT pola, a nie inwentarz sklepu.
     *
     * @var array<int, string>
     */
    private const MODULES = [
        'Calmfox_Watch',
        'Magento_Backend',
        'Magento_Catalog',
        'Magento_Checkout',
        'Magento_Sales',
        'Magento_TwoFactorAuth',
    ];

    /** @return array<string, mixed> */
    public static function health(): array
    {
        $checks = CheckNormalizer::normalize([
            CheckResult::ok('db', 'Baza danych', null, 4),
            CheckResult::warn('disk', 'Miejsce na dysku', 'Sklep zajmuje 41,8 GB. To 87% z podanego limitu konta 48,0 GB. Poza tym miejsce zajmują poczta i pozostałe strony na koncie.'),
            CheckResult::ok('smtp', 'Wysyłka e-mail (SMTP)', 'Serwer smtp.example.com:587 przyjmuje połączenia. To test połączenia, nie doręczenia wiadomości.', 134),
            CheckResult::ok('magento_cron', 'Zadania cykliczne (cron)', 'Ostatnie zadanie zakończone powodzeniem 47 s temu.', 6),
            CheckResult::warn('indexers', 'Indeksy sklepu', 'Nieaktualne indeksy: catalog_product_price. Kupujący mogą widzieć stare ceny, stany magazynowe albo niepełne kategorie. Przebuduj poleceniem bin/magento indexer:reindex.', 9),
            CheckResult::ok('app_cache', 'Pamięć podręczna aplikacji (Redis)', null, 2),
            CheckResult::ok('checkout', 'Ścieżka zakupowa', 'Każdy z 2 włączonych widoków sklepu ma czynną metodę płatności i dostawy.', 12),
            CheckResult::ok('search_engine', 'Wyszukiwarka (Elasticsearch / OpenSearch)', 'Stan klastra: green (silnik opensearch).', 21),
            CheckResult::ok('queue', 'Kolejki wiadomości', 'W kolejce czeka 6 wiadomości, najstarsza od 38 s.', 5),
        ]);

        return PayloadBuilder::health(
            self::VERSION,
            $checks,
            PayloadBuilder::site('2.4.7-p3', '8.3.14', self::VERSION, ['core' => 0, 'plugins' => 7, 'themes' => 0]),
            [
                'adminCount' => 4,
                'adminsFingerprint' => AdminFingerprint::of(['1:sklep.admin', '4:magazyn', '9:marketing', '12:obsluga'], self::SECRET),
                'newestAdminAt' => '2026-07-30T09:12:00+00:00',
                'pluginCount' => \count(self::MODULES),
                'pluginsFingerprint' => AdminFingerprint::of(self::MODULES, self::SECRET),
                'activePlugins' => self::MODULES,
                // `autoUpdates` nie ma i nie będzie: Magento nie aktualizuje się samo,
                // a wartość w tym polu znaczyłaby „sprawdzone".
            ]
        );
    }

    /** @return array<string, mixed> */
    public static function security(): array
    {
        $checks = CheckNormalizer::normalize([
            CheckResult::ok('admin_count', 'Liczba administratorów', 'Aktywnych kont w panelu: 4.'),
            CheckResult::ok('admin_login', 'Konta o domyślnym loginie', 'Brak kont o domyślnych loginach.'),
            CheckResult::warn('admin_path', 'Adres panelu administracyjnego', 'Panel stoi pod przewidywalnym adresem /admin. Automaty pukają do domyślnych adresów panelu nieprzerwanie, więc to najtańsza zmiana, jaką da się tu zrobić.'),
            CheckResult::ok('two_factor', 'Logowanie dwuskładnikowe (2FA)', 'Logowanie dwuskładnikowe do panelu jest włączone.'),
            CheckResult::ok('app_mode', 'Tryb pracy aplikacji', 'Tryb produkcyjny.'),
            CheckResult::ok('debug_display', 'Ujawnianie szczegółów błędów', 'Błędy nie są wyświetlane odwiedzającym.'),
            CheckResult::ok('https', 'Szyfrowanie HTTPS', 'HTTPS wymuszone w sklepie i w panelu.'),
            CheckResult::warn('php_version', 'Wersja PHP', 'PHP 8.2.20 dostaje już tylko poprawki bezpieczeństwa. Zaplanuj przejście wyżej.'),
            CheckResult::ok('config_perms', 'Uprawnienia plików konfiguracji', 'Sprawdzone pliki (2) nie są zapisywalne dla innych.'),
            CheckResult::ok('dir_perms', 'Uprawnienia katalogów', 'Katalogi zapisywalne wyłącznie dla właściciela.'),
            CheckResult::ok('crypt_key', 'Klucz szyfrujący', 'Klucz szyfrujący jest własny i odpowiednio długi.'),
            CheckResult::ok('dev_packages', 'Pakiety deweloperskie', 'Brak pakietów deweloperskich w wydaniu.'),
            CheckResult::ok('pending_updates', 'Zaległe aktualizacje', 'Pakietów do aktualizacji: 7.'),
        ]);

        return PayloadBuilder::security(self::VERSION, $checks, [
            [
                'kind' => 'plugin',
                'name' => 'vendor/platnosci-online',
                'from' => '3.4.1',
                'to' => '3.4.2',
                'at' => '2026-08-18T21:35:00+00:00',
                'mode' => 'manual',
                'by' => null,
            ],
            [
                'kind' => 'core',
                'name' => 'magento/product-community-edition',
                'from' => '2.4.7-p2',
                'to' => '2.4.7-p3',
                'at' => '2026-08-18T21:35:00+00:00',
                'mode' => 'manual',
                'by' => null,
            ],
            [
                'kind' => 'core',
                'name' => 'PHP',
                'from' => '8.1.29',
                'to' => '8.2.20',
                'at' => '2026-06-02T04:11:00+00:00',
                'mode' => 'manual',
                'by' => null,
            ],
        ]);
    }
}
