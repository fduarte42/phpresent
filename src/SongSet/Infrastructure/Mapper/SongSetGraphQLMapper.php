<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Infrastructure\Mapper;

use Phpresent\SongSet\Application\DTO\RemoteSongSetItemRecord;
use Phpresent\SongSet\Application\DTO\RemoteSongSetRecord;

/**
 * Translates SongbookPro's GraphQL response shape into `RemoteSongSetRecord`
 * value objects. Isolated here so the GraphQL schema's field names/casing
 * never leak beyond this class.
 */
final class SongSetGraphQLMapper
{
    /**
     * @param array<string, mixed> $node one `songSets.edges[].node` entry
     */
    public function mapSongSet(array $node): RemoteSongSetRecord
    {
        /** @var array<int, array<string, mixed>> $itemNodes */
        $itemNodes = $node['items'] ?? [];

        return new RemoteSongSetRecord(
            externalId: (string) $node['id'],
            name: (string) $node['name'],
            revision: (string) ($node['revision'] ?? $node['updatedAt'] ?? '0'),
            checksum: (string) ($node['checksum'] ?? ''),
            items: array_map($this->mapItem(...), $itemNodes),
            serviceDate: isset($node['serviceDate']) ? (string) $node['serviceDate'] : null,
            notes: isset($node['notes']) ? (string) $node['notes'] : null,
        );
    }

    /**
     * @param array<string, mixed> $node one `items[]` entry
     */
    private function mapItem(array $node): RemoteSongSetItemRecord
    {
        return new RemoteSongSetItemRecord(
            songExternalId: (string) $node['songId'],
            sourcePosition: (int) $node['position'],
            transposedKey: isset($node['transposedKey']) ? (string) $node['transposedKey'] : null,
            notes: isset($node['notes']) ? (string) $node['notes'] : null,
        );
    }
}
