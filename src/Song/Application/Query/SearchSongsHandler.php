<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\Query;

use Phpresent\Song\Application\DTO\SongDto;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;

final readonly class SearchSongsHandler
{
    public function __construct(private SongRepositoryInterface $songRepository)
    {
    }

    /**
     * @return list<SongDto>
     */
    public function __invoke(SearchSongsQuery $query): array
    {
        $songs = trim($query->query) === ''
            ? $this->songRepository->all($query->limit, $query->offset)
            : $this->songRepository->search($query->query, $query->limit, $query->offset);

        return array_map(static fn ($song) => SongDto::fromEntity($song), $songs);
    }
}
