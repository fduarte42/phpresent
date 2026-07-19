<?php

declare(strict_types=1);

namespace Phpresent\Song\Infrastructure\SongbookPro;

use Generator;
use Phpresent\Song\Application\DTO\RemoteSongRecord;
use Phpresent\Song\Application\Service\SongSourceInterface;
use Phpresent\Song\Infrastructure\Mapper\SongGraphQLMapper;
use Phpresent\SongbookPro\Infrastructure\GraphQL\GraphQLClientInterface;

/**
 * Walks SongbookPro's `songs` GraphQL connection page by page, yielding one
 * `RemoteSongRecord` at a time so callers never need to hold the full
 * catalogue in memory.
 */
final readonly class SongSource implements SongSourceInterface
{
    private const string QUERY = <<<'GRAPHQL'
        query Songs($after: String, $updatedSince: String, $first: Int!) {
          songs(after: $after, updatedSince: $updatedSince, first: $first) {
            pageInfo { hasNextPage endCursor }
            edges {
              node {
                id
                title
                authors
                copyright
                ccli
                key
                tempo
                capo
                tags
                format
                revision
                checksum
                metadata
                sections {
                  position
                  type
                  label
                  content
                  chordProSource
                }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private GraphQLClientInterface $client,
        private SongGraphQLMapper $mapper,
        private int $pageSize = 50,
    ) {
    }

    public function fetchAll(?string $updatedSince = null): iterable
    {
        return $this->walk($updatedSince);
    }

    /**
     * @return Generator<int, RemoteSongRecord>
     */
    private function walk(?string $updatedSince): Generator
    {
        $after = null;

        do {
            $response = $this->client->query(self::QUERY, [
                'after' => $after,
                'updatedSince' => $updatedSince,
                'first' => $this->pageSize,
            ]);

            /** @var array{pageInfo: array{hasNextPage: bool, endCursor: ?string}, edges: array<int, array{node: array<string, mixed>}>} $songs */
            $songs = $response->data['songs'];

            foreach ($songs['edges'] as $edge) {
                yield $this->mapper->mapSong($edge['node']);
            }

            $after = $songs['pageInfo']['endCursor'];
            $hasNextPage = $songs['pageInfo']['hasNextPage'];
        } while ($hasNextPage && $after !== null);
    }
}
