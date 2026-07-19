<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\Command;

final readonly class SyncSongsResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
    ) {
    }
}
