<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\DTO;

use Phpresent\Shared\Domain\Plugin\Bible\BiblePassageRecord;

final readonly class BiblePassageDto
{
    /**
     * @param list<BibleVerseDto> $verses
     */
    public function __construct(
        public string $book,
        public int $chapter,
        public array $verses,
    ) {
    }

    public static function fromRecord(BiblePassageRecord $record): self
    {
        return new self(
            book: $record->book,
            chapter: $record->chapter,
            verses: array_map(BibleVerseDto::fromRecord(...), $record->verses),
        );
    }
}
