<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\Cache;

/**
 * @phpstan-type CachedResponse array{etag: string, data: array<string, mixed>}
 */
interface ETagCacheInterface
{
    /**
     * @return CachedResponse|null
     */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function put(string $key, string $etag, array $data): void;
}
