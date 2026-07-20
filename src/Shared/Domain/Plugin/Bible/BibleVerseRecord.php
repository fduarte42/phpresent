<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Plugin\Bible;

final readonly class BibleVerseRecord
{
    public function __construct(
        public string $book,
        public int $chapter,
        public int $verse,
        public string $text,
    ) {
    }
}
