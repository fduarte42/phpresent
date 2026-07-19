<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Command;

final readonly class ReorderSongSetItemsCommand
{
    /**
     * @param list<string> $orderedItemIds
     */
    public function __construct(
        public string $songSetId,
        public array $orderedItemIds,
    ) {
    }
}
