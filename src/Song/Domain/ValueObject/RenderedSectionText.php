<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\ValueObject;

/**
 * Plain, display-ready lines extracted from one `SongSection` — chords and
 * format markup removed, but not yet wrapped or paginated into slides. That
 * step is a display concern, not a song-content concern; see
 * `Phpresent\Presentation\Application\Service\SlideComposer`.
 */
final readonly class RenderedSectionText
{
    /**
     * @param string[] $lines
     */
    public function __construct(public array $lines)
    {
    }
}
