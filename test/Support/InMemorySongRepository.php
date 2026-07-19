<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemorySongRepository implements SongRepositoryInterface
{
    /** @var array<string, Song> */
    private array $songs = [];

    public function get(UuidInterface $id): ?Song
    {
        return $this->songs[$id->toString()] ?? null;
    }

    public function findByExternalId(string $externalId): ?Song
    {
        foreach ($this->songs as $song) {
            if ($song->externalId() === $externalId) {
                return $song;
            }
        }

        return null;
    }

    public function save(Song $song): void
    {
        $this->songs[$song->id()->toString()] = $song;
    }

    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        return array_values(array_filter(
            $this->songs,
            static fn (Song $song): bool => str_contains(mb_strtolower($song->title()), mb_strtolower($query)),
        ));
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_values($this->songs);
    }

    public function count(): int
    {
        return count($this->songs);
    }
}
