<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Command;

final readonly class SyncSongSetsCommand
{
    public function __construct(public bool $forceFullSync = false)
    {
    }
}
