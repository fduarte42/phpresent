<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Plugin\Bible;

final readonly class BiblePassageRecord
{
    /**
     * @param list<BibleVerseRecord> $verses
     */
    public function __construct(
        public string $book,
        public int $chapter,
        public array $verses,
    ) {
    }
}
