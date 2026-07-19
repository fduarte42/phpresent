<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\DTO;

use Phpresent\SongSet\Domain\Entity\SongSetItem;

final readonly class SongSetItemDto
{
    public function __construct(
        public string $id,
        public string $songExternalId,
        public int $position,
        public ?string $transposedKey,
        public ?string $notes,
        public ?string $songTitle,
        public ?string $songDefaultKey,
    ) {
    }

    /**
     * The referenced Song is resolved by the caller (see
     * GetSongSetHandler) via SongRepositoryInterface — SongSetItem itself
     * only knows the song's external id, never a Song entity, per §17.1.
     */
    public static function fromEntity(SongSetItem $item, ?string $songTitle, ?string $songDefaultKey): self
    {
        return new self(
            id: $item->id()->toString(),
            songExternalId: $item->songExternalId(),
            position: $item->effectivePosition(),
            transposedKey: $item->transposedKey(),
            notes: $item->notes(),
            songTitle: $songTitle,
            songDefaultKey: $songDefaultKey,
        );
    }
}
