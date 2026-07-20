<?php

declare(strict_types=1);

namespace Phpresent\Song\Infrastructure\Mapper;

use Phpresent\Song\Application\DTO\RemoteSongRecord;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\SongbookPro\Infrastructure\GraphQL\LibraryItem;

/**
 * Translates a `SONG`-type `LibraryItem` (§6.2) into a `RemoteSongRecord`.
 *
 * Two things the §6.2 reverse-engineering session could not confirm keep
 * this mapper deliberately narrow:
 *
 * - The observed `SONG` payload is known to be partial ("field list is
 *   partial, response was truncated during capture") — only `title` and
 *   `tempo` are mapped to typed `RemoteSongRecord` fields for that reason;
 *   everything else in the raw payload is preserved verbatim in `metadata`
 *   (the passthrough field `Song` already has for exactly this situation,
 *   SDD §4) rather than guessed at.
 * - How a `SONG_VARIANT` item (which carries the actual lyric/chord
 *   `content`) references the `SONG` it belongs to was never captured, so
 *   this mapper does not attempt to join them — a song synced today has no
 *   sections until that link is confirmed from real traffic and this class
 *   is extended to consume `SONG_VARIANT` items too.
 * - SongbookPro's `dataItems` items (SDD §6.2) carry no per-item revision
 *   token, only a page-level `timestamp` used for delta paging (see
 *   `DeltaFetcher`) — `revision`/`checksum` are therefore computed locally
 *   from the raw payload rather than read from the API, which is enough to
 *   detect drift (`Song::hasDiverged()`) even without a server-issued token.
 */
final class SongGraphQLMapper
{
    /**
     * @return RemoteSongRecord|null null for a tombstone (`deleted: true`)
     *                               or a `SONG` item with no decodable data
     */
    public function mapSong(LibraryItem $item): ?RemoteSongRecord
    {
        if ($item->deleted || $item->data === null) {
            return null;
        }

        $data = $item->data;
        $checksum = hash('xxh128', json_encode($data, JSON_THROW_ON_ERROR));

        $title = $data['title'] ?? '';
        $tempo = $data['tempo'] ?? null;

        return new RemoteSongRecord(
            externalId: $item->id,
            title: is_scalar($title) ? (string) $title : '',
            authors: $this->splitArtist($data['artist'] ?? null),
            format: LyricFormat::PlainText,
            revision: $checksum,
            checksum: $checksum,
            sections: [],
            tempo: is_scalar($tempo) ? (int) $tempo : null,
            metadata: $data,
        );
    }

    /**
     * @return string[]
     */
    private function splitArtist(mixed $artist): array
    {
        if (!is_string($artist) || trim($artist) === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $artist))));
    }
}
