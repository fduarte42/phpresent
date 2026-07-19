<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Command;

final readonly class SyncSongSetsResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
    ) {
    }
}
