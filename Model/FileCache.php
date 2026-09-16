<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

/**
 * Pamięć podręczna wyników w plikach obok stanu (patrz Core\FileCache: nie
 * wolno jej trzymać w pamięci podręcznej Magento, bo tę czyści każde wdrożenie,
 * a jeden z checków bada właśnie ją). Tu tylko podanie katalogu z kontenera.
 */
class FileCache extends \Calmfox\Watch\Core\FileCache
{
    public function __construct(Paths $paths)
    {
        parent::__construct($paths->state());
    }
}
