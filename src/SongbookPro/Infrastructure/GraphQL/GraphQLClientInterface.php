<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

use Phpresent\SongbookPro\Domain\Exception\SongbookProApiException;

interface GraphQLClientInterface
{
    /**
     * Executes a single GraphQL query/mutation against SongbookPro Groups,
     * transparently applying rate limiting, retries, and ETag-based
     * conditional requests.
     *
     * @param array<string, mixed> $variables
     *
     * @throws SongbookProApiException
     */
    public function query(string $query, array $variables = []): GraphQLResponse;
}
