<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Query;

use Phpresent\SongSet\Application\DTO\SongSetDto;
use Phpresent\SongSet\Application\DTO\SongSetItemDto;
use Phpresent\SongSet\Domain\Entity\SongSetItem;
use Phpresent\SongSet\Domain\Repository\SongSetRepositoryInterface;

final readonly class SearchSongSetsHandler
{
    public function __construct(private SongSetRepositoryInterface $songSetRepository)
    {
    }

    /**
     * @return list<SongSetDto>
     */
    public function __invoke(SearchSongSetsQuery $query): array
    {
        $songSets = trim($query->query) === ''
            ? $this->songSetRepository->all($query->limit, $query->offset)
            : $this->songSetRepository->search($query->query, $query->limit, $query->offset);

        return array_map(
            static fn ($songSet) => SongSetDto::fromEntity(
                $songSet,
                array_map(
                    static fn (SongSetItem $item) => SongSetItemDto::fromEntity($item, songTitle: null, songDefaultKey: null),
                    $songSet->items(),
                ),
            ),
            $songSets,
        );
    }
}
