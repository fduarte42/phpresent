<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Command;

use Phpresent\SongSet\Application\DTO\SongSetDto;
use Phpresent\SongSet\Application\Query\GetSongSetHandler;
use Phpresent\SongSet\Domain\Repository\SongSetRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class ReorderSongSetItemsHandler
{
    public function __construct(
        private SongSetRepositoryInterface $songSetRepository,
        private GetSongSetHandler $getSongSetHandler,
    ) {
    }

    public function __invoke(ReorderSongSetItemsCommand $command): ?SongSetDto
    {
        if (!Uuid::isValid($command->songSetId)) {
            return null;
        }

        $songSet = $this->songSetRepository->get(Uuid::fromString($command->songSetId));

        if ($songSet === null) {
            return null;
        }

        $songSet->reorder($command->orderedItemIds);
        $this->songSetRepository->save($songSet);

        return $this->getSongSetHandler->toDto($songSet);
    }
}
