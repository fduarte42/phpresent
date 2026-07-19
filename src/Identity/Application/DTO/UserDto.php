<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\DTO;

use Phpresent\Identity\Domain\Entity\User;

final readonly class UserDto
{
    /**
     * @param string[] $roleIds
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public array $roleIds,
        public bool $isActive,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->id()->toString(),
            email: $user->email(),
            displayName: $user->displayName(),
            roleIds: $user->roleIds(),
            isActive: $user->isActive(),
            createdAt: $user->createdAt()->format(DATE_ATOM),
        );
    }
}
