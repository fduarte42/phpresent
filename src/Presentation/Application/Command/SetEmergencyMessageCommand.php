<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

final readonly class SetEmergencyMessageCommand
{
    public function __construct(public ?string $message)
    {
    }
}
