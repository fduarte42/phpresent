<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Query;

final readonly class SearchBibleQuery
{
    public function __construct(
        public string $translationId,
        public string $query,
        public int $limit = 20,
    ) {
    }
}
