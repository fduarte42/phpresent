<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\DTO;

use Phpresent\Song\Domain\ValueObject\SectionType;

/**
 * Normalized representation of one song section as returned by
 * SongbookPro's GraphQL API, before being turned into a domain
 * `SongSection`. Produced by `SongGraphQLMapper`.
 */
final readonly class RemoteSongSectionRecord
{
    public function __construct(
        public int $position,
        public SectionType $type,
        public string $content,
        public ?string $label = null,
        public ?string $chordProSource = null,
    ) {
    }
}
