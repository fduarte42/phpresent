<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

final readonly class GraphQLResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
    ) {
    }
}
