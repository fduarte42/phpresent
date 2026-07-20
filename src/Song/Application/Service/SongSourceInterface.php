<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\Service;

use Phpresent\Song\Application\DTO\RemoteSongRecord;

/**
 * Port through which the Song Application layer reads songs from
 * SongbookPro, without knowing anything about GraphQL. Implemented by
 * `Phpresent\Song\Infrastructure\SongbookPro\SongSource`.
 */
interface SongSourceInterface
{
    /**
     * @param string|null $updatedSince ATOM-format timestamp; null means a
     *                                  full walk of the catalogue.
     * @return iterable<RemoteSongRecord>
     */
    public function fetchAll(?string $updatedSince = null): iterable;

    /**
     * ATOM-format timestamp reflecting the newest data SongbookPro reported
     * during the most recent `fetchAll()` call — including the case where
     * nothing changed, since the delta cursor still needs to advance to
     * "now" so the next sync doesn't re-scan the same window. For
     * persisting as the next call's `$updatedSince`. Null if `fetchAll()`
     * has not been called yet.
     */
    public function lastSyncedAt(): ?string;
}
