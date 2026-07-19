<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Query;

final readonly class GetSongSetQuery
{
    public function __construct(public string $id)
    {
    }
}
