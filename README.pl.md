# Calmfox Watch dla Magento 2

[English](README.md) · **Polski**

Moduł monitoringu wnętrza sklepu Magento. Wystawia jeden sekretny adres
kontrolny, który odpytuje monitoring Calmfox Watch, i realizuje ten sam
kontrakt, co wtyczka WordPressa oraz pakiety dla Neosa i Syliusa: ten sam
kształt odpowiedzi, ten sam podpis, ta sama droga parowania.

Model jest „pull": moduł nie wysyła nic z siebie poza rejestracją, parowaniem
i rozłączeniem. Reszta to odpowiedzi na pytania monitoringu.

## O Calmfox Watch

[Calmfox Watch](https://watch.calmfox.net) to usługa monitoringu stron. Z
zewnątrz sprawdza dostępność, bezpieczeństwo, aktualność oprogramowania,
działanie treści i szybkość, a wyniki łączy w jedną ocenę kondycji strony od 0
do 100, z wyjaśnieniem każdego straconego punktu i zaleceniem, co zrobić.
Pilnuje też ważności domeny i certyfikatu, zmian w DNS i niedziałających
odnośników, a po potwierdzeniu problemu wysyła powiadomienie.

Sam Calmfox Watch widzi sklep tak jak klient. Ten moduł dodaje widok z
wnętrza Magento: stan usług za sklepem, higienę konfiguracji i historię zmian
pakietów. Usługa nie dostaje żadnych haseł do panelu administracyjnego.

- Calmfox Watch: <https://watch.calmfox.net>
- Packagist: <https://packagist.org/packages/calmfox/watch-magento>

## Zrzuty ekranu

![Kafelek na pulpicie](docs/screenshots/dashboard-tile.png)

*Kafelek z kondycją na pulpicie panelu administracyjnego: pierścień z wynikiem,
liczniki sprawdzeń i trzy najpilniejsze sprawy.*

![Stan usług](docs/screenshots/service-health.png)

*Stan usług na ekranie modułu.*

![Bezpieczeństwo](docs/screenshots/security.png)

*Sprawdzenia higieny bezpieczeństwa, każde z gotową do skopiowania poprawką.*

## Wymagania

| Składnik | Zakres |
| --- | --- |
| Magento | 2.4.x (Open Source i Adobe Commerce) |
| PHP | 8.1 i wyżej |
| Tryb pracy | dowolny, ale sekcja bezpieczeństwa ocenia go pod kątem produkcji |

Moduł nie wymaga RabbitMQ, Redisa ani wyszukiwarki: sprawdza to, co w sklepie
faktycznie jest, a o resztę nie pyta.

## Instalacja

Moduł jest w publicznym katalogu pakietów Composera (Packagist) jako
`calmfox/watch-magento`. Tam, gdzie sklep nie może z niego korzystać, instaluje się
go z paczki `calmfox-watch-magento.zip`, którą podaje panel Calmfox Watch
(Integracje, przycisk „Pobierz dla Magento 2"). W paczce jest jeden katalog:
`calmfox-watch/`.

### Droga 1: Composer (zalecana)

```bash
composer require calmfox/watch-magento

bin/magento module:enable Calmfox_Watch
bin/magento setup:upgrade
bin/magento setup:di:compile     # tylko w trybie produkcyjnym
bin/magento cache:flush
```

Aktualizacja: `composer update calmfox/watch-magento`, a potem
`bin/magento setup:upgrade` i przeczyszczenie pamięci podręcznej.

### Droga 2: paczka w katalogu app/code

```bash
mkdir -p app/code/Calmfox
unzip calmfox-watch-magento.zip -d app/code/Calmfox
mv app/code/Calmfox/calmfox-watch app/code/Calmfox/Watch

bin/magento module:enable Calmfox_Watch
bin/magento setup:upgrade
bin/magento setup:di:compile     # tylko w trybie produkcyjnym
bin/magento cache:flush
```

Nazwa katalogu docelowego nie jest tu dowolna. Magento wczytuje pliki
`app/code/*/*/registration.php` (lista wzorców leży w
`app/etc/registration_globlist.php`), a `registration.php` modułu melduje go jako
`Calmfox_Watch`. Composer nie bierze przy tej drodze udziału w niczym.

### Droga 3: Composerem z rozpakowanej paczki

Dla wdrożeń, w których kod spoza rdzenia ma siedzieć w `vendor`:

```bash
mkdir -p pakiety && unzip calmfox-watch-magento.zip -d pakiety
composer config repositories.calmfox-watch '{"type":"path","url":"./pakiety/calmfox-watch","options":{"symlink":false}}'
composer require calmfox/watch-magento:@dev

bin/magento module:enable Calmfox_Watch
bin/magento setup:upgrade
bin/magento setup:di:compile     # tylko w trybie produkcyjnym
bin/magento cache:flush
```

Trzy miejsca, w których łatwo się potknąć:

- **`"symlink": false`** każe Composerowi skopiować pliki. Bez tego katalog
  w `vendor` jest wyłącznie dowiązaniem do `pakiety` i zniknie razem z nim.
- **Rozpakowany katalog zostaje w projekcie** (i w repozytorium, jeżeli wdrożenie
  idzie z gita). Composer czyta go przy każdym `composer install`, więc jego
  skasowanie wywróci następne wdrożenie.
- **`@dev` przy nazwie modułu jest konieczne.** `composer.json` paczki świadomie
  nie ma pola `version` (Composer wylicza wersję z tagu repozytorium, a paczka
  tagu nie ma), więc repozytorium typu `path` melduje ją jako `dev-main`.

Aktualizacja z paczki: rozpakowanie nowszej w to samo miejsce (przy drodze 2 podmiana
plików w `app/code/Calmfox/Watch`), a potem `bin/magento setup:upgrade`
i przeczyszczenie pamięci podręcznej.

### Adres kontrolny musi być publiczny

Powstaje trasa `GET /calmfox-watch/health`. To monitoring nas odpytuje, a nie
odwrotnie, więc nie ma sesji, którą mógłby się wykazać: autoryzacją jest sekret
w parametrze `key`, porównywany funkcją `hash_equals`.

Trasy sklepowe w Magento są publiczne z natury, więc zwykle nie trzeba nic
robić. Sprawdź jednak trzy rzeczy, bo to one blokują ten adres najczęściej:

- **Zapora aplikacyjna (WAF) i reguły serwera WWW**: nietypowa ścieżka
  z długim parametrem bywa odrzucana automatem.
- **Pełna pamięć podręczna stron i Varnish**: odpowiedź ma nagłówek
  `Cache-Control: no-store`, więc ani wbudowana pamięć podręczna Magento,
  ani domyślna konfiguracja Varnisha jej nie zapiszą. Jeżeli przed sklepem
  stoi inna warstwa buforująca (CDN, cudzy pośrednik), wyklucz z niej ścieżkę
  `/calmfox-watch/`: monitoring ma dostawać stan z tej chwili, nie sprzed godziny.
- **Tryb konserwacji**: na czas wdrożenia Magento oddaje 503 na wszystkim.
  To poprawne zachowanie i monitoring je zobaczy, warto więc planować okno
  serwisowe w panelu Calmfox Watch.

Ekran w panelu i polecenie `calmfox:watch:status` wykonują samokontrolę pętlą
zwrotną i powiedzą wprost, jeżeli adres jest niedostępny z samego serwera.

### Połączenie z panelem

Panel administracyjny: pozycja **Calmfox Watch** w głównym pasku menu, zaraz
za pulpitem (uprawnienie `Calmfox_Watch::watch`, więc rola bez tego zasobu
ekranu nie zobaczy). Pulpit panelu dostaje przy okazji kafelek z kondycją:
status, liczby sprawdzeń i najwyżej trzy najpilniejsze sprawy, z odnośnikiem
do pełnego ekranu. Kafelek widzi wyłącznie rola z tym samym uprawnieniem.

Trzy drogi, wszystkie kończą się tak samo:

1. **Połącz przez watch.calmfox.net**: wychodzimy do panelu, tam logowanie albo
   założenie konta, i wracamy tutaj z kluczem instalacyjnym.
2. **Pakiet Free z ekranu**: podajesz adres e-mail, konto powstaje od razu.
3. **Wiersz poleceń**, gdy wdrożenie jest skryptowe:

```bash
# nowe konto w pakiecie Free
bin/magento calmfox:watch:register wlasciciel@sklep.pl

# albo dopięcie do istniejącej strony w panelu (klucz z ekranu Integracje)
bin/magento calmfox:watch:pair fxp_live_0123456789abcdef
```

Adres sklepu bierzemy z konfiguracji (`web/secure/base_url`, w drugiej
kolejności `web/unsecure/base_url`), więc polecenia działają też w CLI, gdzie
nie ma żądania HTTP. Oba wypisują adres kontrolny, który zgłaszają do panelu,
więc od razu widać, czy jest poprawny.

> **Panel na osobnej domenie**: przycisk „Połącz przez Calmfox Watch" znika.
> Powrót z panelu niesie jawny klucz instalacyjny, więc wolno nam wrócić
> wyłącznie pod panel administracyjny łączonej domeny. Przy panelu pod innym
> adresem zostaje parowanie kluczem, które działa tak samo.

## Konfiguracja

Wszystko ma sensowne wartości domyślne i nie ma osobnej sekcji w Stores →
Configuration. To decyzja, nie przeoczenie: adres API i katalog stanu muszą
być znane także wtedy, gdy baza nie odpowiada, a to jest właśnie ten moment,
w którym monitoring ma pracować.

Zmienia się je zmienną środowiskową albo wpisem w `app/etc/env.php`:

```php
return [
    // ...
    'calmfox_watch' => [
        // Adres API. Zmienna środowiskowa: CALMFOX_WATCH_API_URL.
        'api_url' => 'https://watch.calmfox.net',

        // Katalog stanu. Zmienna środowiskowa: CALMFOX_WATCH_STATE_DIR.
        // Domyślnie var/calmfox-watch.
        'state_dir' => '/var/www/sklep/shared/calmfox-watch',

        // Polecenie Composera dla calmfox:watch:updates.
        'composer_binary' => 'composer',
    ],
];
```

Limit dysku konta hostingowego ustawia się na ekranie w panelu sklepu, bo to
jedyna liczba, której serwer nie zna, a klient ma ją w umowie albo w panelu
hostingu.

## Stan, sekret i wdrożenia z katalogiem na wydanie

Stan (sekret adresu kontrolnego, znacznik parowania, historia wersji,
policzone aktualizacje) leży w **pliku**, nie w bazie i nie w konfiguracji
Magento: `var/calmfox-watch/state.json`, prawa 600, zapis atomowy.

Powody są dwa i oba są praktyczne. Kontraktowy: przy padniętej bazie adres
kontrolny ma odpowiedzieć `db: fail` i kodem 503, a nie zamilknąć. Magentowy:
`core_config_data` idzie przez pamięć podręczną konfiguracji, którą wdrożenie
czyści w połowie pracy.

> **Uwaga przy wdrożeniach typu „nowy katalog na każde wydanie"** (Deployer,
> Capistrano, pipeline deployment, symlink `current`): katalog stanu MUSI być
> współdzielony między wydaniami. Inaczej każde wdrożenie tworzy nowy sekret,
> adres kontrolny zapamiętany w panelu przestaje działać i monitoring zgłasza
> milczący sklep. Historia zmian wersji też zaczyna się wtedy od zera.
>
> `var/` zwykle i tak jest współdzielony. Jeżeli nie jest, ustaw
> `CALMFOX_WATCH_STATE_DIR=/var/www/sklep/shared/calmfox-watch`.

Wymiana sekretu (przycisk „Wymień klucz") działa z oknem 15 minut: nowy sekret
obowiązuje od razu, poprzedni jest honorowany jeszcze kwadrans, więc nieudane
przepięcie w panelu nie zrywa monitoringu.

## Zadania cykliczne

Moduł dokłada jedno własne zadanie do harmonogramu Magento
(`calmfox_watch_reconcile`, 4:17 w nocy): tanie uzgodnienie migawki wersji
pakietów, bez sieci. Dzięki niemu wdrożenie zrobione poza sklepem zostawia
ślad w historii nawet wtedy, gdy monitoring akurat nie pytał o sekcję
bezpieczeństwa.

Liczenia zaległych aktualizacji świadomie tam nie ma: wymaga Composera
i wyjścia do repozytoriów pakietów, a proces sklepu nie ma po co tam chodzić.
Idzie do crona systemowego, obok właściwego crona Magento:

```cron
# cron Magento (to on wysyła maile o zamówieniach i przelicza reguły cenowe)
* * * * *  cd /var/www/sklep && php bin/magento cron:run >/dev/null 2>&1

# zaległe aktualizacje pakietów dla Calmfox Watch (raz na dobę, potrzebuje sieci)
15 3 * * * cd /var/www/sklep && php bin/magento calmfox:watch:updates -q
```

Sprawdzenie `magento_cron` pilnuje pierwszego z tych wpisów: pyta harmonogram
Magento, kiedy ostatnie zadanie skończyło się powodzeniem.

## Polecenia

| Polecenie | Do czego |
| --- | --- |
| `calmfox:watch:status` | Stan połączenia, adres kontrolny, obie sekcje sprawdzeń |
| `calmfox:watch:register <email>` | Zakłada konto Free i łączy sklep |
| `calmfox:watch:pair [token]` | Łączy z istniejącą stroną w panelu |
| `calmfox:watch:disconnect` | Kończy monitoring wnętrza i mówi o tym panelowi |
| `calmfox:watch:updates` | Liczy zaległe aktualizacje, do crona systemowego |
| `calmfox:watch:health [--section=security]` | Wypisuje payload lokalnie, bez sieci |

Usuwasz moduł? Uruchom najpierw `calmfox:watch:disconnect`. Calmfox Watch
traktuje ciszę jako sygnał i po trzech nieudanych odpytaniach otworzy incydent, więc lepiej
powiedzieć mu wprost „kończę".

## Co sprawdzamy

### Sekcja `health` (monitoring pyta co minutę)

| Identyfikator | Co sprawdza |
| --- | --- |
| `db` | Zapytanie kontrolne przez połączenie Magento, z pomiarem czasu. Brak bazy to `fail`, nie wyjątek. |
| `disk` | Prawo zapisu do `var/`, `pub/media` i `generated/`, rozmiar instalacji, zajętość względem podanego limitu konta. |
| `smtp` | Połączenie z serwerem poczty (TCP, powitanie 220, EHLO). To test POŁĄCZENIA, nie doręczenia. |
| `magento_cron` | Kiedy ostatnie zadanie cykliczne skończyło się powodzeniem, ile było błędów i pominięć w ostatniej dobie. |
| `indexers` | Które indeksy są nieaktualne. Sklep z nieaktualnym indeksem działa i pokazuje wczorajsze ceny. |
| `app_cache` | Zapis i odczyt klucza kontrolnego w pamięci podręcznej aplikacji, razem z nazwą silnika. |
| `checkout` | Czy w każdym włączonym widoku sklepu da się kupić: metoda płatności (poza Zero Subtotal) i metoda dostawy. |
| `search_engine` | Stan klastra Elasticsearch albo OpenSearch. W 2.4 bez niego padają wyszukiwarka i listingi. |
| `queue` | Zaległości w kolejkach bazodanowych. Opcjonalny: przy pustej tabeli (albo RabbitMQ) check w ogóle nie powstaje. |

### Sekcja `security` (monitoring pyta raz na dobę)

`admin_count`, `admin_login`, `admin_path`, `two_factor`, `app_mode`,
`debug_display`, `https`, `php_version`, `config_perms`, `dir_perms`,
`crypt_key`, `dev_packages`, `pending_updates`.

Magentowe w tej liście są cztery: adres panelu razem z kluczem w adresach
(`admin_path`), logowanie dwuskładnikowe (`two_factor`), tryb wdrożenia
(`app_mode`) i klucz szyfrujący z `app/etc/env.php`, którym zaszyfrowane są
dane dostępu do bramek płatniczych (`crypt_key`).

To podstawowa higiena, a nie audyt. Nie skanujemy złośliwego kodu, nie liczymy
sum kontrolnych plików platformy i nie robimy kopii zapasowych.

### Czego świadomie nie robimy

- Nie wysyłamy listy pakietów z wersjami. `site.updates` to same liczby, bo
  spis „co i w jakiej wersji" jest gotową mapą dziur dla atakującego. Nazwy
  jadą wyłącznie tam, gdzie są istotą funkcji: w historii zmian wersji oraz
  jako skład WŁĄCZONYCH modułów (`signals.activePlugins`, bez wersji). Ten
  drugi wyjątek jest świadomy i ma cenę: kto zdobędzie sekretny adres
  kontrolny, zobaczy listę modułów. Bez nazw zdarzenie o wyłączonym module
  brzmiałoby „coś się zmieniło", a wtedy nie da się na nie zareagować.
- Nie udajemy automatycznych aktualizacji. Magento ich nie ma, więc pole
  `signals.autoUpdates` w ogóle nie jedzie, zamiast wieźć wartość, która
  znaczyłaby „sprawdzone".
- Nie wysyłamy loginów. Zamiast nich jedzie liczba kont administracyjnych
  i jednokierunkowy odcisk ich zbioru, solony sekretem instalacji. Panel
  wykrywa ZMIANĘ składu, nie tożsamość.
- Nie zaglądamy w dane sprzedażowe. Sprawdzenie `checkout` patrzy wyłącznie
  na konfigurację widoków sklepu, nigdy na zamówienia ani obroty.
- Nie zgadujemy liczb, których nie znamy. Dopóki nikt nie uruchomił
  `calmfox:watch:updates`, pole `updates` nie jedzie w ogóle. Zero znaczyłoby
  „sprawdzone, nie ma czego aktualizować", a to byłaby nieprawda.
- Nie udajemy, że widzimy pocztę wysyłaną przez moduł innego producenta.
  Znamy wyłącznie ustawienia `system/smtp` i tak to opisujemy.

## Własne sprawdzenia

Usługi, o których wie tylko właściciel sklepu (integracja z magazynem, mostek
do systemu księgowego, demon synchronizacji cen), dopina się własną klasą
i jednym wpisem w `etc/di.xml` swojego modułu:

```php
namespace Vendor\Sklep\Monitoring;

use Calmfox\Watch\Check\HealthCheckInterface;
use Calmfox\Watch\Core\CheckResult;

class WarehouseCheck implements HealthCheckInterface
{
    public function run(): ?CheckResult
    {
        $start = microtime(true);
        $socket = @fsockopen('127.0.0.1', 5672, $errno, $error, 2);
        $ms = (int) round((microtime(true) - $start) * 1000);

        if (!\is_resource($socket)) {
            return CheckResult::fail('warehouse', 'Integracja z magazynem', 'Usługa nie przyjmuje połączeń.', $ms);
        }
        fclose($socket);

        return CheckResult::ok('warehouse', 'Integracja z magazynem', null, $ms);
    }
}
```

```xml
<virtualType name="Calmfox\Watch\Model\HealthRunner" type="Calmfox\Watch\Check\CheckRunner">
    <arguments>
        <argument name="checks" xsi:type="array">
            <item name="warehouse" xsi:type="object">Vendor\Sklep\Monitoring\WarehouseCheck</item>
        </argument>
    </arguments>
</virtualType>
```

Wpisy scalają się z naszymi, więc powyższe DOKŁADA sprawdzenie. Wpis o nazwie,
której już używamy, nadpisuje nasz: tak wyłącza się pojedynczy check, gdy
w danym sklepie nie ma sensu.

Dwie zasady, obie z kontraktu:

1. Zwróć `null`, gdy sprawdzenie nie dotyczy tej instalacji. Nie wysyłamy
   „ok" o czymś, czego nie ma.
2. Trzymaj krótki, twardy limit czasu. Adres kontrolny odpowiada co minutę
   i nie może zamulić sklepu.

Identyfikator spoza katalogu parametrów trafi do panelu z etykietą z payloadu
i notką „usługa dopięta własnym rozszerzeniem".

## Historia zmian wersji

Magento nie ma haka aktualizacji: moduły wchodzą Composerem, zwykle z innej
maszyny, a `setup:upgrade` dokłada tylko zmiany w bazie. Dlatego moduł
porównuje migawki `vendor/composer/installed.php` (plus wersję PHP) przy
budowaniu sekcji `security`, w nocnym zadaniu i przy `calmfox:watch:updates`.

Konsekwencje, które trzeba znać:

- Znacznik `at` to **czas wykrycia** zmiany, a nie czas wdrożenia. Zwykle
  różnią się o minuty. Do zdania „awaria zaczęła się po aktualizacji modułu X"
  to wystarcza, do rozliczania wdrożeń co do sekundy nie.
- Historia zaczyna się od instalacji modułu. Wcześniejszych zmian nie da się
  odtworzyć, bo nie ma z czego.
- Zmiana metapakietu wydania, `magento/framework` albo PHP zapisuje się jako
  `core`, pozostałe pakiety jako `plugin`. Motywy Magento są zwykłymi
  pakietami, więc nie mają osobnej kategorii. Bufor to 200 wpisów.

## Prywatność

Do Calmfox jedzie: domena sklepu, podany adres e-mail (tylko przy zakładaniu
konta) i dane diagnostyczne opisane wyżej: statusy sprawdzeń z opisami, wersje
platformy i PHP, liczby zaległych aktualizacji, liczba i odcisk kont
administracyjnych oraz historia zmian wersji pakietów. Żadnych treści sklepu,
zamówień, danych klientów, loginów ani haseł.

## Testy

Rdzeń modułu (`Core/`) jest wolny od Magento: to zwykłe klasy PHP. Dzięki temu
kontrakt z panelem da się przetestować bez kontenera, bazy i zainstalowanego
sklepu.

Testy mają własny autoloader PSR-4 (`tests/bootstrap.php`), więc moduł nie wymaga
własnego `composer install`. Wystarczy dowolny PHPUnit 10 lub nowszy:

```bash
phpunit -c phpunit.xml.dist
```

Testy pilnują między innymi: agregacji `ok`/`warn`/`fail`, odrzucenia złego
klucza, ważności poprzedniego sekretu w oknie rotacji, jednorazowości znacznika
łączenia przez panel, zgodności podpisu z weryfikacją po stronie panelu,
pominięcia pola `updates` przy braku danych, porównywania migawek wersji oraz
rozbioru ustawień poczty Magento.

Przykładowe odpowiedzi obu sekcji leżą w `docs/sample-health.json`
i `docs/sample-security.json`. Powstają z tego samego kodu, który odpowiada
monitoringowi, a test pilnuje, żeby się nie rozjechały. Regeneracja po
świadomej zmianie kontraktu:

```bash
CALMFOX_WRITE_SAMPLES=1 phpunit --filter SamplePayloads
```

## Granice ochrony

Odpowiedź podpisujemy kluczem instalacji (HMAC-SHA256 nad znacznikiem
jednorazowym, czasem wygenerowania i dokładnymi bajtami treści). To odcina
tanie ataki: podstawiony plik statyczny, odpowiedź z pamięci podręcznej,
powtórkę sprzed przejęcia sklepu. Kto ma pełną kontrolę nad serwerem, ma też
sekret i potrafi podpisać kłamstwo. Podpis nie zastępuje odzyskiwania serwera
i tak o nim mówimy.

## Licencja

MIT, zobacz [LICENSE](LICENSE).
