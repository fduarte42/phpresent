<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\DTO;

use Phpresent\Presentation\Domain\ValueObject\SlideDeck;

final readonly class SlideDeckDto
{
    /**
     * @param list<SlideDto> $slides
     */
    public function __construct(
        public string $sourceType,
        public ?string $sourceId,
        public array $slides,
    ) {
    }

    public static function fromValueObject(SlideDeck $deck): self
    {
        return new self(
            sourceType: $deck->sourceType->value,
            sourceId: $deck->sourceId,
            slides: array_map(SlideDto::fromValueObject(...), $deck->slides),
        );
    }
}
