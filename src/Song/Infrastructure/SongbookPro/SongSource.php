<?php

declare(strict_types=1);

namespace Phpresent\Song\Infrastructure\SongbookPro;

use Generator;
use Phpresent\Song\Application\DTO\RemoteSongRecord;
use Phpresent\Song\Application\Service\SongSourceInterface;
use Phpresent\Song\Infrastructure\Mapper\SongGraphQLMapper;
use Phpresent\SongbookPro\Infrastructure\GraphQL\DeltaFetcher;
use Phpresent\SongbookPro\Infrastructure\GraphQL\EpochMillis;
use Phpresent\SongbookPro\Infrastructure\GraphQL\GraphQLClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Walks SongbookPro's generic `dataItems` delta query (§6.2) via
 * `DeltaFetcher`, yielding one `RemoteSongRecord` per `SONG`-type item.
 *
 * Deletions (`deleted: true` tombstones) and `SONG_VARIANT` items are
 * observed but not yet acted on — `Song` has no "retired" state to sync a
 * tombstone into yet, and `SongGraphQLMapper` cannot safely join a variant
 * to its song (see its docblock). Both are logged so the gap stays visible
 * rather than silently dropped.
 */
final class SongSource implements SongSourceInterface
{
    private const string TYPE_SONG = 'SONG';

    private ?string $lastTimestamp = null;

    public function __construct(
        private readonly GraphQLClientInterface $client,
        private readonly SongGraphQLMapper $mapper,
        private readonly LoggerInterface $logger,
        private readonly string $library,
    ) {
    }

    public function fetchAll(?string $updatedSince = null): iterable
    {
        $this->lastTimestamp = null;

        return $this->walk($updatedSince);
    }

    public function lastSyncedAt(): ?string
    {
        return $this->lastTimestamp;
    }

    /**
     * @return Generator<int, RemoteSongRecord>
     */
    private function walk(?string $updatedSince): Generator
    {
        $fetcher = new DeltaFetcher($this->client, $this->library);
        $since = $updatedSince !== null ? EpochMillis::fromAtom($updatedSince) : null;

        foreach ($fetcher->fetch($since) as $item) {
            if ($item->type !== self::TYPE_SONG) {
                continue;
            }

            if ($item->deleted) {
                $this->logger->warning('Skipping SongbookPro song tombstone — Song has no retired state yet', [
                    'externalId' => $item->id,
                ]);

                continue;
            }

            $record = $this->mapper->mapSong($item);

            if ($record === null) {
                $this->logger->warning('Skipping SONG item with no decodable data', ['externalId' => $item->id]);

                continue;
            }

            yield $record;
        }

        $lastTimestamp = $fetcher->lastTimestamp();
        $this->lastTimestamp = $lastTimestamp !== null ? EpochMillis::toAtom($lastTimestamp) : null;
    }
}
