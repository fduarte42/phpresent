<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\Cache;

use Psr\SimpleCache\CacheInterface;

final class PsrETagCache implements ETagCacheInterface
{
    private const int TTL_SECONDS = 86_400;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function get(string $key): ?array
    {
        /** @var array{etag: string, data: array<string, mixed>}|null $cached */
        $cached = $this->cache->get($this->cacheKey($key));

        return $cached;
    }

    public function put(string $key, string $etag, array $data): void
    {
        $this->cache->set($this->cacheKey($key), ['etag' => $etag, 'data' => $data], self::TTL_SECONDS);
    }

    private function cacheKey(string $key): string
    {
        return 'songbookpro.etag.' . hash('xxh128', $key);
    }
}
