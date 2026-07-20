<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\ValueObject;

final readonly class SlideDeck
{
    /**
     * @param list<Slide> $slides
     */
    public function __construct(
        public SlideSourceType $sourceType,
        public ?string $sourceId,
        public array $slides,
    ) {
    }

    public function count(): int
    {
        return count($this->slides);
    }

    /**
     * @return array{sourceType: string, sourceId: ?string, slides: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'sourceType' => $this->sourceType->value,
            'sourceId' => $this->sourceId,
            'slides' => array_map(static fn (Slide $slide): array => $slide->toArray(), $this->slides),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $sourceType = $data['sourceType'] ?? null;
        $sourceId = $data['sourceId'] ?? null;
        /** @var list<mixed> $rawSlides */
        $rawSlides = is_array($data['slides'] ?? null) ? $data['slides'] : [];

        return new self(
            sourceType: is_string($sourceType) ? SlideSourceType::from($sourceType) : SlideSourceType::Blank,
            sourceId: is_string($sourceId) ? $sourceId : null,
            slides: array_values(array_map(
                static fn (mixed $slide): Slide => Slide::fromArray(is_array($slide) ? $slide : []),
                $rawSlides,
            )),
        );
    }
}
