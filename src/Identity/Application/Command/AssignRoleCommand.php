<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

final readonly class AssignRoleCommand
{
    public function __construct(
        public ?string $actorUserId,
        public string $userId,
        public string $roleId,
    ) {
    }
}
