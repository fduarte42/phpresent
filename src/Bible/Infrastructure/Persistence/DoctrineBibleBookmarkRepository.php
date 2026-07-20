<?php

declare(strict_types=1);

namespace Phpresent\Bible\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Phpresent\Bible\Domain\Repository\BibleBookmarkRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineBibleBookmarkRepository implements BibleBookmarkRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?BibleBookmark
    {
        return $this->entityManager->find(BibleBookmark::class, $id);
    }

    public function save(BibleBookmark $bookmark): void
    {
        $this->entityManager->persist($bookmark);
        $this->entityManager->flush();
    }

    public function remove(BibleBookmark $bookmark): void
    {
        $this->entityManager->remove($bookmark);
        $this->entityManager->flush();
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        /** @var list<BibleBookmark> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(BibleBookmark::class, 'b')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
