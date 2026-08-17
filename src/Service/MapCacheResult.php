<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

final readonly class MapCacheResult
{
    public function __construct(
        public string $path,
        public int $sizeMeters,
    ) {
    }
}
