<?php

declare(strict_types=1);

namespace Calmfox\Watch\Core;

/**
 * Pakiet strony znany z chwili połączenia. Źródłem prawdy jest panel i tak też
 * mówi o tym ekran modułu: pakiet mógł się w międzyczasie zmienić, a moduł
 * dowie się o tym dopiero przy następnym parowaniu.
 */
final class PlanState
{
    /**
     * Brak pakietu traktujemy jak Free: strona dodana z modułu wchodzi właśnie
     * na Free, a starsze instalacje mogą nie mieć tego pola w stanie. Pomyłka
     * w tę stronę pokazuje o jedno zaproszenie do pakietu za dużo, w drugą
     * ukryłaby je przed tym, kto płaci zero.
     */
    public static function isFree(mixed $plan): bool
    {
        $name = mb_strtolower(trim((string) $plan));

        return '' === $name || 'free' === $name;
    }
}
