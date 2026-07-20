<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Query;

final readonly class ListBookmarksQuery
{
    public function __construct(
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
