<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Song\Application\DTO\RemoteSongRecord;
use Phpresent\Song\Application\Service\SongSourceInterface;

final class FakeSongSource implements SongSourceInterface
{
    /** @var list<RemoteSongRecord> */
    private array $records;

    public ?string $lastRequestedSince = null;

    /**
     * @param list<RemoteSongRecord> $records
     */
    public function __construct(array $records = [], private ?string $nextSyncedAt = null)
    {
        $this->records = $records;
    }

    public function fetchAll(?string $updatedSince = null): iterable
    {
        $this->lastRequestedSince = $updatedSince;

        return $this->records;
    }

    public function lastSyncedAt(): ?string
    {
        return $this->nextSyncedAt;
    }
}
