<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\Repository;

use Phpresent\Song\Domain\Entity\Song;
use Ramsey\Uuid\UuidInterface;

interface SongRepositoryInterface
{
    public function get(UuidInterface $id): ?Song;

    public function findByExternalId(string $externalId): ?Song;

    public function save(Song $song): void;

    /**
     * @return list<Song>
     */
    public function search(string $query, int $limit = 50, int $offset = 0): array;

    /**
     * @return list<Song>
     */
    public function all(int $limit = 50, int $offset = 0): array;

    public function count(): int;
}
