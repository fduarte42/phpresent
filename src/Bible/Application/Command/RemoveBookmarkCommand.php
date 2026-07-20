<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Command;

final readonly class RemoveBookmarkCommand
{
    public function __construct(public string $id)
    {
    }
}
