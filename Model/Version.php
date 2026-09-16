<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

/**
 * Jedyne źródło prawdy o wersji modułu. Stąd czyta payload (`plugin`), ekran
 * w panelu sklepu i skrypt budujący paczkę, więc podbicie w jednym miejscu
 * wystarczy. Świadomie nie ma pola „version" w composer.json: Composer wylicza
 * je z tagu, a paczka do ręcznego wgrania tagu nie ma i byłoby co rozjeżdżać.
 */
final class Version
{
    public const NUMBER = '1.3.1';
}
