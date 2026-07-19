<?php

declare(strict_types=1);

namespace Phpresent\Identity\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        /** @var list<User> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.displayName', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
