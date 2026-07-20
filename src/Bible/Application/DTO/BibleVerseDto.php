<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\DTO;

use Phpresent\Shared\Domain\Plugin\Bible\BibleVerseRecord;

final readonly class BibleVerseDto
{
    public function __construct(
        public string $book,
        public int $chapter,
        public int $verse,
        public string $text,
    ) {
    }

    public static function fromRecord(BibleVerseRecord $record): self
    {
        return new self($record->book, $record->chapter, $record->verse, $record->text);
    }
}
