<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Repository;

use Phpresent\Identity\Domain\Entity\User;
use Ramsey\Uuid\UuidInterface;

interface UserRepositoryInterface
{
    public function get(UuidInterface $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    /**
     * @return list<User>
     */
    public function all(int $limit = 50, int $offset = 0): array;
}
