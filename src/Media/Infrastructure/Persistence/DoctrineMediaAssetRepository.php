<?php

declare(strict_types=1);

namespace Phpresent\Media\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineMediaAssetRepository implements MediaAssetRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?MediaAsset
    {
        return $this->entityManager->find(MediaAsset::class, $id);
    }

    public function save(MediaAsset $asset): void
    {
        $this->entityManager->persist($asset);
        $this->entityManager->flush();
    }

    public function remove(MediaAsset $asset): void
    {
        $this->entityManager->remove($asset);
        $this->entityManager->flush();
    }

    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        /** @var list<MediaAsset> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MediaAsset::class, 'm')
            ->where('LOWER(m.filename) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('m.uploadedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        /** @var list<MediaAsset> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MediaAsset::class, 'm')
            ->orderBy('m.uploadedAt', 'DESC')
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
            ->select('COUNT(m.id)')
            ->from(MediaAsset::class, 'm')
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }
}
