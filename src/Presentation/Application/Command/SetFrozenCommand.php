<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

final readonly class SetFrozenCommand
{
    public function __construct(public bool $frozen)
    {
    }
}
