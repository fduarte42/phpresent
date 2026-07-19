<?php

declare(strict_types=1);

namespace Phpresent\Song\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineSongRepository implements SongRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?Song
    {
        return $this->entityManager->find(Song::class, $id);
    }

    public function findByExternalId(string $externalId): ?Song
    {
        return $this->entityManager
            ->getRepository(Song::class)
            ->findOneBy(['externalId' => $externalId]);
    }

    public function save(Song $song): void
    {
        $this->entityManager->persist($song);
        $this->entityManager->flush();
    }

    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Song::class, 's')
            ->where('LOWER(s.title) LIKE :query')
            ->orWhere('LOWER(s.ccli) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('s.title', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<Song> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        /** @var list<Song> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Song::class, 's')
            ->orderBy('s.title', 'ASC')
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
            ->from(Song::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }
}
