<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Persistence;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sync_state')]
class SyncState
{
    #[ORM\Id]
    #[ORM\Column(name: 'entity_type', type: 'string', length: 64, unique: true)]
    private string $entityType;

    #[ORM\Column(name: 'last_synced_at', type: 'datetime_immutable')]
    private DateTimeImmutable $lastSyncedAt;

    public function __construct(string $entityType, DateTimeImmutable $lastSyncedAt)
    {
        $this->entityType = $entityType;
        $this->lastSyncedAt = $lastSyncedAt;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function lastSyncedAt(): DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->lastSyncedAt = $at;
    }
}
