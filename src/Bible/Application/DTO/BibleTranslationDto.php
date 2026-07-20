<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\DTO;

use Phpresent\Shared\Domain\Plugin\Bible\BibleTranslationSummary;

final readonly class BibleTranslationDto
{
    public function __construct(
        public string $providerId,
        public string $id,
        public string $name,
        public string $abbreviation,
        public string $language,
    ) {
    }

    public static function fromRecord(string $providerId, BibleTranslationSummary $summary): self
    {
        return new self(
            providerId: $providerId,
            id: $summary->id,
            name: $summary->name,
            abbreviation: $summary->abbreviation,
            language: $summary->language,
        );
    }
}
