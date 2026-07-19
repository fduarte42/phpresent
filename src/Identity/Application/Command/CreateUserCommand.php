<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

final readonly class CreateUserCommand
{
    /**
     * @param string[] $roleIds
     */
    public function __construct(
        public ?string $actorUserId,
        public string $email,
        public string $password,
        public string $displayName,
        public array $roleIds = [],
    ) {
    }
}
