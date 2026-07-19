<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function get(UuidInterface $id): ?User
    {
        return $this->users[$id->toString()] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $this->users[$user->id()->toString()] = $user;
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_values($this->users);
    }
}
