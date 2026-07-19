<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\DTO;

/**
 * Normalized representation of one song set item as returned by
 * SongbookPro's GraphQL API, before being merged onto a domain
 * `SongSetItem`. Produced by `SongSetGraphQLMapper`.
 */
final readonly class RemoteSongSetItemRecord
{
    public function __construct(
        public string $songExternalId,
        public int $sourcePosition,
        public ?string $transposedKey = null,
        public ?string $notes = null,
    ) {
    }
}
