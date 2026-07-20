<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Command;

final readonly class CreateBookmarkCommand
{
    public function __construct(
        public string $translationId,
        public string $book,
        public int $chapter,
        public ?int $startVerse = null,
        public ?int $endVerse = null,
        public ?string $label = null,
    ) {
    }
}
