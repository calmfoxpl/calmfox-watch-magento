<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

use Calmfox\Watch\Core\AdminFingerprint;
use Calmfox\Watch\Core\SecretManager;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Skład WŁĄCZONYCH modułów sklepu. To jest magentowy odpowiednik listy
 * aktywnych wtyczek WordPressa (WTYCZKI.md, sekcja 2): moduł leżący w
 * app/code albo w vendorze, ale wyłączony w config.php, nie działa i nie
 * ma prawa liczyć się jako włączony.
 *
 * Sygnały jadą w sekcji `health`, odpytywanej co minutę, a nie w `security`
 * czytanej raz na dobę: wyłączenie modułu zabezpieczającego (choćby
 * dwuskładnikowego logowania) to klasyczny krok po przejęciu panelu i wolimy
 * wiedzieć o nim w ciągu minut niż nazajutrz.
 *
 * Nazwy wysyłamy świadomie i mówimy o tym wprost w README: bez nich zdarzenie
 * brzmiałoby „coś się zmieniło", a klient potrzebuje wiedzieć, KTÓRY moduł
 * zniknął. Ceną jest to, że kto zdobędzie sekretny adres kontrolny, zobaczy
 * listę modułów; ten sam adres i tak wydaje wersję platformy, wersję PHP
 * i historię aktualizacji z nazwami pakietów.
 *
 * Pola `autoUpdates` NIE wysyłamy: Magento nie aktualizuje się samo, a wartość
 * w tym polu znaczyłaby „sprawdzone", zamiast „nie ma czego sprawdzać".
 */
class ModuleSignals
{
    /** Tyle nazw przyjmuje hub. Odcisk liczymy z CAŁEJ listy, więc zmiana poza setką też się wykryje. */
    private const NAMES_LIMIT = 100;

    /** Hub przycina dłuższe nazwy, więc przycinamy je sami, żeby odcisk zgadzał się z listą. */
    private const NAME_LENGTH = 80;

    public function __construct(
        private readonly ModuleListInterface $modules,
        private readonly SecretManager $secrets,
    ) {
    }

    /** @return array{pluginCount: ?int, pluginsFingerprint: ?string, activePlugins: ?array<int, string>} */
    public function signals(): array
    {
        $names = $this->names();
        if ([] === $names) {
            // Pusty spis znaczy „nie udało się odczytać", a nie „sklep nie ma
            // modułów": Magento bez modułów nie istnieje. Pusta lista wysłana
            // jako fakt kazałaby hubowi ogłosić, że zniknęły wszystkie naraz.
            return ['pluginCount' => null, 'pluginsFingerprint' => null, 'activePlugins' => null];
        }

        return [
            'pluginCount' => \count($names),
            // Ta sama formuła co przy kontach administracyjnych (HMAC z sekretu
            // instalacji, 32 znaki hex). Klasa nazywa się AdminFingerprint, bo
            // tam powstała; powielanie formuły dałoby dwa miejsca do rozjechania.
            'pluginsFingerprint' => AdminFingerprint::of($names, $this->secrets->secret()),
            'activePlugins' => \array_slice($names, 0, self::NAMES_LIMIT),
        ];
    }

    /** @return array<int, string> posortowane nazwy włączonych modułów, bez duplikatów */
    private function names(): array
    {
        try {
            $raw = $this->modules->getNames();
        } catch (\Throwable) {
            return [];
        }

        $names = [];
        foreach ($raw as $name) {
            $name = trim(mb_substr((string) $name, 0, self::NAME_LENGTH));
            if ('' !== $name) {
                $names[$name] = true;
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }
}
