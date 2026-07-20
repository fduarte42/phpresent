<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\ValueObject;

/**
 * Pagination rules for `SlideComposer` (SDD §7): max lines, max chars,
 * smart wrap, split-on-section-boundary-first. A display/operator concern,
 * not a song-content concern — kept separate from
 * `Song\Domain\Service\SectionRenderer`, which only extracts plain text.
 */
final readonly class RenderOptions
{
    public function __construct(
        public int $maxLinesPerSlide = 4,
        public int $maxCharsPerLine = 40,
    ) {
    }

    public static function default(): self
    {
        return new self();
    }
}
