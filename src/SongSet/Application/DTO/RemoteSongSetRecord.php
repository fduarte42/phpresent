<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\DTO;

/**
 * Normalized representation of one song set as returned by SongbookPro's
 * GraphQL API, before being turned into (or merged onto) a domain
 * `SongSet` aggregate. Produced by `SongSetGraphQLMapper`; consumed by
 * `SyncSongSetsHandler`.
 */
final readonly class RemoteSongSetRecord
{
    /**
     * @param list<RemoteSongSetItemRecord> $items
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public string $revision,
        public string $checksum,
        public array $items,
        public ?string $serviceDate = null,
        public ?string $notes = null,
    ) {
    }
}
