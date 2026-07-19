<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemoryRoleRepository implements RoleRepositoryInterface
{
    /** @var array<string, Role> */
    private array $roles = [];

    public function get(UuidInterface $id): ?Role
    {
        return $this->roles[$id->toString()] ?? null;
    }

    public function findByName(string $name): ?Role
    {
        foreach ($this->roles as $role) {
            if ($role->name() === $name) {
                return $role;
            }
        }

        return null;
    }

    public function save(Role $role): void
    {
        $this->roles[$role->id()->toString()] = $role;
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_values($this->roles);
    }
}
