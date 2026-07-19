<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Infrastructure\SongbookPro;

use Generator;
use Phpresent\SongbookPro\Infrastructure\GraphQL\GraphQLClientInterface;
use Phpresent\SongSet\Application\DTO\RemoteSongSetRecord;
use Phpresent\SongSet\Application\Service\SongSetSourceInterface;
use Phpresent\SongSet\Infrastructure\Mapper\SongSetGraphQLMapper;

/**
 * Walks SongbookPro's `songSets` GraphQL connection page by page, yielding
 * one `RemoteSongSetRecord` at a time so callers never need to hold the
 * full set catalogue in memory.
 */
final readonly class SongSetSource implements SongSetSourceInterface
{
    private const string QUERY = <<<'GRAPHQL'
        query SongSets($after: String, $updatedSince: String, $first: Int!) {
          songSets(after: $after, updatedSince: $updatedSince, first: $first) {
            pageInfo { hasNextPage endCursor }
            edges {
              node {
                id
                name
                serviceDate
                notes
                revision
                checksum
                items {
                  songId
                  position
                  transposedKey
                  notes
                }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private GraphQLClientInterface $client,
        private SongSetGraphQLMapper $mapper,
        private int $pageSize = 50,
    ) {
    }

    public function fetchAll(?string $updatedSince = null): iterable
    {
        return $this->walk($updatedSince);
    }

    /**
     * @return Generator<int, RemoteSongSetRecord>
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

            /** @var array{pageInfo: array{hasNextPage: bool, endCursor: ?string}, edges: array<int, array{node: array<string, mixed>}>} $songSets */
            $songSets = $response->data['songSets'];

            foreach ($songSets['edges'] as $edge) {
                yield $this->mapper->mapSongSet($edge['node']);
            }

            $after = $songSets['pageInfo']['endCursor'];
            $hasNextPage = $songSets['pageInfo']['hasNextPage'];
        } while ($hasNextPage && $after !== null);
    }
}
