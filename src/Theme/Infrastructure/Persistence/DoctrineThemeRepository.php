<?php

declare(strict_types=1);

namespace Phpresent\Theme\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineThemeRepository implements ThemeRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?Theme
    {
        return $this->entityManager->find(Theme::class, $id);
    }

    public function save(Theme $theme): void
    {
        $this->entityManager->persist($theme);
        $this->entityManager->flush();
    }

    public function remove(Theme $theme): void
    {
        $this->entityManager->remove($theme);
        $this->entityManager->flush();
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        /** @var list<Theme> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Theme::class, 't')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
