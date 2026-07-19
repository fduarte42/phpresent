<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Repository;

use Phpresent\Identity\Domain\Entity\Role;
use Ramsey\Uuid\UuidInterface;

interface RoleRepositoryInterface
{
    public function get(UuidInterface $id): ?Role;

    public function findByName(string $name): ?Role;

    public function save(Role $role): void;

    /**
     * @return list<Role>
     */
    public function all(int $limit = 50, int $offset = 0): array;
}
