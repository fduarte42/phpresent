<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

final readonly class CreateRoleCommand
{
    /**
     * @param string[] $permissions
     */
    public function __construct(
        public ?string $actorUserId,
        public string $name,
        public array $permissions = [],
    ) {
    }
}
