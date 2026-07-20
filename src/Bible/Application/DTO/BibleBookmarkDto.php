<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\DTO;

use Phpresent\Bible\Domain\Entity\BibleBookmark;

final readonly class BibleBookmarkDto
{
    public function __construct(
        public string $id,
        public string $translationId,
        public string $book,
        public int $chapter,
        public ?int $startVerse,
        public ?int $endVerse,
        public ?string $label,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(BibleBookmark $bookmark): self
    {
        return new self(
            id: $bookmark->id()->toString(),
            translationId: $bookmark->translationId(),
            book: $bookmark->book(),
            chapter: $bookmark->chapter(),
            startVerse: $bookmark->startVerse(),
            endVerse: $bookmark->endVerse(),
            label: $bookmark->label(),
            createdAt: $bookmark->createdAt()->format(DATE_ATOM),
        );
    }
}
