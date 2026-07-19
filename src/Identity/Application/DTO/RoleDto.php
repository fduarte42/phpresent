<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\DTO;

use Phpresent\Identity\Domain\Entity\Role;

final readonly class RoleDto
{
    /**
     * @param string[] $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
    ) {
    }

    public static function fromEntity(Role $role): self
    {
        return new self(
            id: $role->id()->toString(),
            name: $role->name(),
            permissions: $role->permissions(),
        );
    }
}
