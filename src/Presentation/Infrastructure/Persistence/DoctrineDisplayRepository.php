<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class DoctrineDisplayRepository implements DisplayRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(UuidInterface $id): ?Display
    {
        return $this->entityManager->find(Display::class, $id);
    }

    public function save(Display $display): void
    {
        $this->entityManager->persist($display);
        $this->entityManager->flush();
    }

    public function remove(Display $display): void
    {
        $this->entityManager->remove($display);
        $this->entityManager->flush();
    }

    public function all(): array
    {
        /** @var list<Display> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Display::class, 'd')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
