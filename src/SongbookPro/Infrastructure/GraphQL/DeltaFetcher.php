<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

use Generator;

/**
 * Walks SongbookPro's generic `dataItems(library, since)` delta query
 * (§6.2), the one query every module's sync goes through — there is no
 * per-entity `songs`/`songSets` query. Pagination is cursor-free: each
 * response carries a `timestamp` that becomes the next page's `since`, and a
 * `hasMore` flag caps how many pages a single sync pass walks.
 *
 * The `since`/`timestamp` values are treated as opaque scalars here — they
 * are only ever fed back verbatim into the next request, never interpreted
 * or converted, so no assumption about their unit is needed for paging
 * itself (only callers that persist a cursor across sync passes need to
 * make that call — see `SongSource`).
 */
final class DeltaFetcher
{
    private const string QUERY = <<<'GRAPHQL'
        query GetDataItemsSince($library: ID!, $since: BigInt) {
          dataItems(library: $library, since: $since) {
            timestamp
            hasMore
            items {
              id
              type
              deleted
              data
            }
          }
        }
        GRAPHQL;

    private ?string $lastTimestamp = null;

    public function __construct(
        private readonly GraphQLClientInterface $client,
        private readonly string $library,
    ) {
    }

    /**
     * @return Generator<int, LibraryItem>
     */
    public function fetch(?string $since): Generator
    {
        $cursor = $since;

        do {
            $response = $this->client->query(self::QUERY, [
                'library' => $this->library,
                'since' => $cursor,
            ]);

            /** @var array{timestamp: string|int, hasMore: bool, items: list<array<string, mixed>>} $page */
            $page = $response->data['dataItems'];

            foreach ($page['items'] as $rawItem) {
                yield LibraryItem::fromGraphQL($rawItem);
            }

            $cursor = (string) $page['timestamp'];
            $hasMore = (bool) $page['hasMore'];
        } while ($hasMore);

        $this->lastTimestamp = $cursor;
    }

    /**
     * The `timestamp` of the last page fetched by `fetch()`, for the caller
     * to persist as the next sync pass's `since`. Only meaningful after
     * `fetch()`'s generator has been fully consumed.
     */
    public function lastTimestamp(): ?string
    {
        return $this->lastTimestamp;
    }
}
