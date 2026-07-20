<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

/**
 * One entry from `dataItems.items` (§6.2) — SongbookPro's generic
 * upsert/tombstone envelope, keyed by an opaque `type` string ("SONG",
 * "SONG_VARIANT", ...) rather than a typed GraphQL field per entity kind.
 */
final readonly class LibraryItem
{
    /**
     * @param array<string, scalar|array<mixed>|null>|null $data null for a
     *        tombstone (`deleted: true`) or a malformed payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public bool $deleted,
        public ?array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $raw one `dataItems.items[]` entry
     */
    public static function fromGraphQL(array $raw): self
    {
        $rawData = $raw['data'] ?? null;
        $decoded = is_string($rawData) ? json_decode($rawData, true) : null;

        return new self(
            id: is_scalar($raw['id']) ? (string) $raw['id'] : '',
            type: is_scalar($raw['type']) ? (string) $raw['type'] : '',
            deleted: (bool) ($raw['deleted'] ?? false),
            data: is_array($decoded) ? $decoded : null,
        );
    }
}
