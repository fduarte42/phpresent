<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

use Phpresent\SongbookPro\Domain\Exception\SongbookProApiException;

interface GraphQLClientInterface
{
    /**
     * Executes a single GraphQL query against SongbookPro Groups,
     * transparently applying auth, rate limiting, and retries. There is no
     * ETag/conditional-request layer — none was observed in real traffic
     * (SDD §6) — and the real endpoint always returns a JSON array (the
     * official client's batching format, §6.1), which implementations must
     * unwrap before returning `data` here.
     *
     * Only reads are implemented — Phpresent never writes song content back
     * to SongbookPro (§1) — so mutation support (`addDataItems`, §6.2) is
     * intentionally not part of this port yet.
     *
     * @param array<string, mixed> $variables
     *
     * @throws SongbookProApiException
     */
    public function query(string $query, array $variables = []): GraphQLResponse;
}
