<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\Query;

use Phpresent\Song\Application\DTO\SongDto;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class GetSongHandler
{
    public function __construct(private SongRepositoryInterface $songRepository)
    {
    }

    public function __invoke(GetSongQuery $query): ?SongDto
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $song = $this->songRepository->get(Uuid::fromString($query->id));

        return $song === null ? null : SongDto::fromEntity($song);
    }
}
