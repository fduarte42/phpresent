<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\SongSet\Application\DTO\RemoteSongSetRecord;
use Phpresent\SongSet\Application\Service\SongSetSourceInterface;

final class FakeSongSetSource implements SongSetSourceInterface
{
    /** @var list<RemoteSongSetRecord> */
    private array $records;

    public ?string $lastRequestedSince = null;

    /**
     * @param list<RemoteSongSetRecord> $records
     */
    public function __construct(array $records = [])
    {
        $this->records = $records;
    }

    public function fetchAll(?string $updatedSince = null): iterable
    {
        $this->lastRequestedSince = $updatedSince;

        return $this->records;
    }
}
