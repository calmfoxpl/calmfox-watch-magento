<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

/**
 * Stan modułu w pliku (patrz Core\StateStore: adres kontrolny ma odpowiadać
 * także przy padniętej bazie). Ta klasa istnieje wyłącznie po to, żeby
 * kontener Magento umiał podać katalog: rdzeń zostaje wolny od frameworka
 * i testowalny bez kontenera.
 */
class StateStore extends \Calmfox\Watch\Core\StateStore
{
    public function __construct(Paths $paths)
    {
        parent::__construct($paths->state());
    }
}
