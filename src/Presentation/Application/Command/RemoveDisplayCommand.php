<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

final readonly class RemoveDisplayCommand
{
    public function __construct(public string $id)
    {
    }
}
