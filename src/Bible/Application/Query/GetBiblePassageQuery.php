<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Query;

final readonly class GetBiblePassageQuery
{
    public function __construct(
        public string $translationId,
        public string $book,
        public int $chapter,
        public ?int $startVerse = null,
        public ?int $endVerse = null,
    ) {
    }
}
