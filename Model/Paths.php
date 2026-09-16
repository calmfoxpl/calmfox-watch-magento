<?php

declare(strict_types=1);

namespace Calmfox\Watch\Model;

use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Ścieżki instalacji w jednym miejscu. Magento pozwala przenieść praktycznie
 * każdy katalog (pub/, var/, generated/ bywają na innym wolumenie), więc
 * pytamy o nie DirectoryList zamiast sklejać je z korzenia projektu.
 *
 * Katalog stanu domyślnie leży w var/calmfox-watch. Przy wdrożeniach
 * z osobnym katalogiem na każde wydanie MUSI być współdzielony między
 * wydaniami, inaczej sklep po każdym wdrożeniu zgłasza się z nowym sekretem
 * i traci historię (opisane w README).
 */
class Paths
{
    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly Settings $settings,
    ) {
    }

    public function root(): string
    {
        return $this->directoryList->getRoot();
    }

    public function var(): string
    {
        return $this->path(DirectoryList::VAR_DIR);
    }

    public function media(): string
    {
        return $this->path(DirectoryList::MEDIA);
    }

    public function pub(): string
    {
        return $this->path(DirectoryList::PUB);
    }

    public function generated(): string
    {
        return $this->path(DirectoryList::GENERATED);
    }

    /** app/etc, czyli env.php i config.php. */
    public function config(): string
    {
        return $this->path(DirectoryList::CONFIG);
    }

    public function vendor(): string
    {
        return $this->root().'/vendor';
    }

    public function state(): string
    {
        $override = $this->settings->stateDir();
        if (null !== $override) {
            return rtrim($override, '/');
        }

        return $this->var().'/calmfox-watch';
    }

    private function path(string $code): string
    {
        try {
            return rtrim((string) $this->directoryList->getPath($code), '/');
        } catch (\Throwable) {
            return '';
        }
    }
}
