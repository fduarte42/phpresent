<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use DateTimeImmutable;
use Phpresent\Shared\Domain\Repository\SyncStateRepositoryInterface;

final class InMemorySyncStateRepository implements SyncStateRepositoryInterface
{
    /** @var array<string, DateTimeImmutable> */
    private array $state = [];

    public function getLastSyncedAt(string $entityType): ?DateTimeImmutable
    {
        return $this->state[$entityType] ?? null;
    }

    public function setLastSyncedAt(string $entityType, DateTimeImmutable $at): void
    {
        $this->state[$entityType] = $at;
    }
}
