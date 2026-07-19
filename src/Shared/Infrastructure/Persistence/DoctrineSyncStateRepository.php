<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Persistence;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Shared\Domain\Repository\SyncStateRepositoryInterface;

final class DoctrineSyncStateRepository implements SyncStateRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getLastSyncedAt(string $entityType): ?DateTimeImmutable
    {
        $state = $this->entityManager->find(SyncState::class, $entityType);

        return $state?->lastSyncedAt();
    }

    public function setLastSyncedAt(string $entityType, DateTimeImmutable $at): void
    {
        $state = $this->entityManager->find(SyncState::class, $entityType);

        if ($state === null) {
            $this->entityManager->persist(new SyncState($entityType, $at));
        } else {
            $state->touch($at);
        }

        $this->entityManager->flush();
    }
}
