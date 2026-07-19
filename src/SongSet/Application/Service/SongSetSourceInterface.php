<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Service;

use Phpresent\SongSet\Application\DTO\RemoteSongSetRecord;

interface SongSetSourceInterface
{
    /**
     * @param string|null $updatedSince ATOM-format timestamp; null means a full walk.
     * @return iterable<RemoteSongSetRecord>
     */
    public function fetchAll(?string $updatedSince = null): iterable;
}
