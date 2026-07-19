<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Repository;

use DateTimeImmutable;

interface SyncStateRepositoryInterface
{
    public function getLastSyncedAt(string $entityType): ?DateTimeImmutable;

    public function setLastSyncedAt(string $entityType, DateTimeImmutable $at): void;
}
