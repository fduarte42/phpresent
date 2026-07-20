<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Phpresent\Bible\Domain\Repository\BibleBookmarkRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemoryBibleBookmarkRepository implements BibleBookmarkRepositoryInterface
{
    /** @var array<string, BibleBookmark> */
    private array $bookmarks = [];

    public function get(UuidInterface $id): ?BibleBookmark
    {
        return $this->bookmarks[$id->toString()] ?? null;
    }

    public function save(BibleBookmark $bookmark): void
    {
        $this->bookmarks[$bookmark->id()->toString()] = $bookmark;
    }

    public function remove(BibleBookmark $bookmark): void
    {
        unset($this->bookmarks[$bookmark->id()->toString()]);
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_values($this->bookmarks);
    }
}
