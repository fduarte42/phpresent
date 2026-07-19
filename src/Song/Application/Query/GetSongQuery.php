<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\Query;

final readonly class GetSongQuery
{
    public function __construct(public string $id)
    {
    }
}
