<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

final readonly class SetFontSizeAdjustCommand
{
    public function __construct(public int $steps)
    {
    }
}
