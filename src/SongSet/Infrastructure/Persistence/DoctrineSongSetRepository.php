<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\SongSet\Domain\Entity\SongSet;
use Phpresent\SongSet\Domain\Repository\SongSetRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineSongSetRepository implements SongSetRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?SongSet
    {
        return $this->entityManager->find(SongSet::class, $id);
    }

    public function findByExternalId(string $externalId): ?SongSet
    {
        return $this->entityManager
            ->getRepository(SongSet::class)
            ->findOneBy(['externalId' => $externalId]);
    }

    public function save(SongSet $songSet): void
    {
        $this->entityManager->persist($songSet);
        $this->entityManager->flush();
    }

    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(SongSet::class, 's')
            ->where('LOWER(s.name) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('s.name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<SongSet> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        /** @var list<SongSet> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(SongSet::class, 's')
            ->orderBy('s.name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function count(): int
    {
        /** @var int $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(SongSet::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }
}
