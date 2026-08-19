<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

final readonly class FeedSyncResult
{
    public function __construct(
        public int $discovered,
        public int $imported,
        public int $alreadyImported,
        public int $failed,
    ) {
    }
}
