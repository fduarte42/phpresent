<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\SongbookPro\Infrastructure\GraphQL\GraphQLClientInterface;
use Phpresent\SongbookPro\Infrastructure\GraphQL\GraphQLResponse;

final class FakeGraphQLClient implements GraphQLClientInterface
{
    /** @var list<array<string, mixed>> */
    public array $recordedVariables = [];

    /**
     * @param list<GraphQLResponse> $responses returned in order, one per call
     */
    public function __construct(private array $responses)
    {
    }

    public function query(string $query, array $variables = []): GraphQLResponse
    {
        $this->recordedVariables[] = $variables;

        $response = array_shift($this->responses);

        if ($response === null) {
            throw new \RuntimeException('FakeGraphQLClient has no more queued responses.');
        }

        return $response;
    }
}
