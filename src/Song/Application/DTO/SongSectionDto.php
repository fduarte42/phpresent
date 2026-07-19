<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\DTO;

use Phpresent\Song\Domain\Entity\SongSection;

final readonly class SongSectionDto
{
    public function __construct(
        public string $id,
        public int $position,
        public string $type,
        public ?string $label,
        public string $content,
    ) {
    }

    public static function fromEntity(SongSection $section): self
    {
        return new self(
            id: $section->id()->toString(),
            position: $section->position(),
            type: $section->type()->value,
            label: $section->label(),
            content: $section->content(),
        );
    }
}
