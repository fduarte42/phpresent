<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\SongSet\Domain\Entity\SongSet;
use Phpresent\SongSet\Domain\Repository\SongSetRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemorySongSetRepository implements SongSetRepositoryInterface
{
    /** @var array<string, SongSet> */
    private array $songSets = [];

    public function get(UuidInterface $id): ?SongSet
    {
        return $this->songSets[$id->toString()] ?? null;
    }

    public function findByExternalId(string $externalId): ?SongSet
    {
        foreach ($this->songSets as $songSet) {
            if ($songSet->externalId() === $externalId) {
                return $songSet;
            }
        }

        return null;
    }

    public function save(SongSet $songSet): void
    {
        $this->songSets[$songSet->id()->toString()] = $songSet;
    }

    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        return array_values(array_filter(
            $this->songSets,
            static fn (SongSet $songSet): bool => str_contains(mb_strtolower($songSet->name()), mb_strtolower($query)),
        ));
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_values($this->songSets);
    }

    public function count(): int
    {
        return count($this->songSets);
    }
}
