<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Command;

final readonly class RemoveThemeCommand
{
    public function __construct(public string $id)
    {
    }
}
