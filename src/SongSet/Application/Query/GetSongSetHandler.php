<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Query;

use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Phpresent\SongSet\Application\DTO\SongSetDto;
use Phpresent\SongSet\Application\DTO\SongSetItemDto;
use Phpresent\SongSet\Domain\Entity\SongSet;
use Phpresent\SongSet\Domain\Entity\SongSetItem;
use Phpresent\SongSet\Domain\Repository\SongSetRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class GetSongSetHandler
{
    public function __construct(
        private SongSetRepositoryInterface $songSetRepository,
        private SongRepositoryInterface $songRepository,
    ) {
    }

    public function __invoke(GetSongSetQuery $query): ?SongSetDto
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $songSet = $this->songSetRepository->get(Uuid::fromString($query->id));

        return $songSet === null ? null : $this->toDto($songSet);
    }

    /**
     * Resolves each item's referenced Song for display. A missing or
     * not-yet-synced Song leaves songTitle/songDefaultKey null rather than
     * failing the whole set — see §17.2.
     */
    public function toDto(SongSet $songSet): SongSetDto
    {
        $items = array_map(
            function (SongSetItem $item): SongSetItemDto {
                $song = $this->songRepository->findByExternalId($item->songExternalId());

                return SongSetItemDto::fromEntity($item, $song?->title(), $song?->defaultKey());
            },
            $songSet->items(),
        );

        return SongSetDto::fromEntity($songSet, $items);
    }
}
