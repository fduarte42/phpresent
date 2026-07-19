<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\DTO;

use Phpresent\SongSet\Domain\Entity\SongSet;

final readonly class SongSetDto
{
    /**
     * @param list<SongSetItemDto> $items
     */
    public function __construct(
        public string $id,
        public string $externalId,
        public string $name,
        public ?string $serviceDate,
        public ?string $notes,
        public array $items,
        public string $syncedAt,
    ) {
    }

    /**
     * @param list<SongSetItemDto> $items
     */
    public static function fromEntity(SongSet $songSet, array $items): self
    {
        return new self(
            id: $songSet->id()->toString(),
            externalId: $songSet->externalId(),
            name: $songSet->name(),
            serviceDate: $songSet->serviceDate()?->format(DATE_ATOM),
            notes: $songSet->notes(),
            items: $items,
            syncedAt: $songSet->syncedAt()->format(DATE_ATOM),
        );
    }
}
