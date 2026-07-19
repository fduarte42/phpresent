<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\DTO;

use Phpresent\Song\Domain\Entity\Song;

final readonly class SongDto
{
    /**
     * @param string[] $authors
     * @param string[] $tags
     * @param list<SongSectionDto> $sections
     */
    public function __construct(
        public string $id,
        public string $externalId,
        public string $title,
        public array $authors,
        public ?string $copyright,
        public ?string $ccli,
        public ?string $defaultKey,
        public ?int $tempo,
        public ?int $capo,
        public array $tags,
        public string $format,
        public array $sections,
        public string $syncedAt,
    ) {
    }

    public static function fromEntity(Song $song): self
    {
        return new self(
            id: $song->id()->toString(),
            externalId: $song->externalId(),
            title: $song->title(),
            authors: $song->authors(),
            copyright: $song->copyright(),
            ccli: $song->ccli(),
            defaultKey: $song->defaultKey(),
            tempo: $song->tempo(),
            capo: $song->capo(),
            tags: $song->tags(),
            format: $song->format()->value,
            sections: array_map(
                static fn ($section) => SongSectionDto::fromEntity($section),
                $song->sections(),
            ),
            syncedAt: $song->syncedAt()->format(DATE_ATOM),
        );
    }
}
