<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\DTO;

use Phpresent\Song\Domain\ValueObject\LyricFormat;

/**
 * Normalized representation of one song as returned by SongbookPro's
 * GraphQL API, before being turned into (or merged onto) a domain `Song`
 * aggregate. Produced by `SongGraphQLMapper`; consumed by `SyncSongsHandler`.
 */
final readonly class RemoteSongRecord
{
    /**
     * @param string[] $authors
     * @param string[] $tags
     * @param list<RemoteSongSectionRecord> $sections
     * @param array<string, scalar|array<mixed>|null> $metadata
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public array $authors,
        public LyricFormat $format,
        public string $revision,
        public string $checksum,
        public array $sections,
        public ?string $copyright = null,
        public ?string $ccli = null,
        public ?string $defaultKey = null,
        public ?int $tempo = null,
        public ?int $capo = null,
        public array $tags = [],
        public array $metadata = [],
    ) {
    }
}
