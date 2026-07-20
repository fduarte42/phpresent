<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

final readonly class SetLyricsHiddenCommand
{
    public function __construct(public bool $hidden)
    {
    }
}
