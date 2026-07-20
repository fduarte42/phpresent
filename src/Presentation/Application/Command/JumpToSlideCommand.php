<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

final readonly class JumpToSlideCommand
{
    public function __construct(public int $index)
    {
    }
}
