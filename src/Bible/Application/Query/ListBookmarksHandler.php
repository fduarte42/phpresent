<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Query;

use Phpresent\Bible\Application\DTO\BibleBookmarkDto;
use Phpresent\Bible\Domain\Repository\BibleBookmarkRepositoryInterface;

final readonly class ListBookmarksHandler
{
    public function __construct(private BibleBookmarkRepositoryInterface $bookmarkRepository)
    {
    }

    /**
     * @return list<BibleBookmarkDto>
     */
    public function __invoke(ListBookmarksQuery $query): array
    {
        return array_map(
            BibleBookmarkDto::fromEntity(...),
            $this->bookmarkRepository->all($query->limit, $query->offset),
        );
    }
}
