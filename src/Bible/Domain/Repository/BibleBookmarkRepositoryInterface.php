<?php

declare(strict_types=1);

namespace Phpresent\Bible\Domain\Repository;

use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Ramsey\Uuid\UuidInterface;

interface BibleBookmarkRepositoryInterface
{
    public function get(UuidInterface $id): ?BibleBookmark;

    public function save(BibleBookmark $bookmark): void;

    public function remove(BibleBookmark $bookmark): void;

    /**
     * @return list<BibleBookmark>
     */
    public function all(int $limit = 50, int $offset = 0): array;
}
