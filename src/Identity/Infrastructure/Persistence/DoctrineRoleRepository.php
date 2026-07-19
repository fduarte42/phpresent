<?php

declare(strict_types=1);

namespace Phpresent\Identity\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineRoleRepository implements RoleRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?Role
    {
        return $this->entityManager->find(Role::class, $id);
    }

    public function findByName(string $name): ?Role
    {
        return $this->entityManager
            ->getRepository(Role::class)
            ->findOneBy(['name' => $name]);
    }

    public function save(Role $role): void
    {
        $this->entityManager->persist($role);
        $this->entityManager->flush();
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        /** @var list<Role> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Role::class, 'r')
            ->orderBy('r.name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
