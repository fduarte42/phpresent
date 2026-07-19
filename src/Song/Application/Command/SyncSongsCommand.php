<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\Command;

final readonly class SyncSongsCommand
{
    public function __construct(public bool $forceFullSync = false)
    {
    }
}
