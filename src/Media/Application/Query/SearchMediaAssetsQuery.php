<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Query;

final readonly class SearchMediaAssetsQuery
{
    public function __construct(
        public string $query = '',
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
