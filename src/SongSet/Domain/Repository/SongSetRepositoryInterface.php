<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Domain\Repository;

use Phpresent\SongSet\Domain\Entity\SongSet;
use Ramsey\Uuid\UuidInterface;

interface SongSetRepositoryInterface
{
    public function get(UuidInterface $id): ?SongSet;

    public function findByExternalId(string $externalId): ?SongSet;

    public function save(SongSet $songSet): void;

    /**
     * @return list<SongSet>
     */
    public function search(string $query, int $limit = 50, int $offset = 0): array;

    /**
     * @return list<SongSet>
     */
    public function all(int $limit = 50, int $offset = 0): array;

    public function count(): int;
}
