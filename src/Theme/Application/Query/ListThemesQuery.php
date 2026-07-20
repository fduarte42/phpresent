<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Query;

final readonly class ListThemesQuery
{
    public function __construct(
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
