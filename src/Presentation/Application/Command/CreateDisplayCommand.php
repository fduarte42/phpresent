<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

final readonly class CreateDisplayCommand
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $name,
        public string $role,
        public array $settings = [],
    ) {
    }
}
