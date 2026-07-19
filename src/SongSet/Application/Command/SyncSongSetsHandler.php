<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Application\Command;

use DateTimeImmutable;
use Phpresent\Shared\Domain\Repository\SyncStateRepositoryInterface;
use Phpresent\SongSet\Application\DTO\RemoteSongSetItemRecord;
use Phpresent\SongSet\Application\DTO\RemoteSongSetRecord;
use Phpresent\SongSet\Application\Service\SongSetSourceInterface;
use Phpresent\SongSet\Domain\Entity\SongSet;
use Phpresent\SongSet\Domain\Exception\InvalidMusicalKeyException;
use Phpresent\SongSet\Domain\Repository\SongSetRepositoryInterface;
use Phpresent\SongSet\Domain\ValueObject\MusicalKey;
use Psr\Log\LoggerInterface;

final readonly class SyncSongSetsHandler
{
    private const string ENTITY_TYPE = 'song_set';

    public function __construct(
        private SongSetSourceInterface $songSetSource,
        private SongSetRepositoryInterface $songSetRepository,
        private SyncStateRepositoryInterface $syncState,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncSongSetsCommand $command): SyncSongSetsResult
    {
        $since = $command->forceFullSync
            ? null
            : $this->syncState->getLastSyncedAt(self::ENTITY_TYPE);

        $syncStartedAt = new DateTimeImmutable();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($this->songSetSource->fetchAll($since?->format(DATE_ATOM)) as $record) {
            $result = $this->syncOne($record, $syncStartedAt);
            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default => $unchanged++,
            };
        }

        $this->syncState->setLastSyncedAt(self::ENTITY_TYPE, $syncStartedAt);

        $this->logger->info('SongbookPro song set sync complete', [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
        ]);

        return new SyncSongSetsResult($created, $updated, $unchanged);
    }

    private function syncOne(RemoteSongSetRecord $record, DateTimeImmutable $now): string
    {
        $serviceDate = $this->parseDate($record->serviceDate);
        $songSet = $this->songSetRepository->findByExternalId($record->externalId);

        if ($songSet === null) {
            $songSet = new SongSet(
                externalId: $record->externalId,
                name: $record->name,
                sourceRevision: $record->revision,
                sourceChecksum: $record->checksum,
                serviceDate: $serviceDate,
                notes: $record->notes,
                now: $now,
            );
            $songSet->syncItems($this->buildItemRecords($record));
            $this->songSetRepository->save($songSet);

            return 'created';
        }

        if (!$songSet->hasDiverged($record->revision, $record->checksum)) {
            return 'unchanged';
        }

        $songSet->applySync(
            name: $record->name,
            sourceRevision: $record->revision,
            sourceChecksum: $record->checksum,
            serviceDate: $serviceDate,
            notes: $record->notes,
            now: $now,
        );
        $songSet->syncItems($this->buildItemRecords($record));
        $this->songSetRepository->save($songSet);

        return 'updated';
    }

    /**
     * @return list<array{songExternalId: string, sourcePosition: int, transposedKey: ?MusicalKey, notes: ?string}>
     */
    private function buildItemRecords(RemoteSongSetRecord $record): array
    {
        return array_map(
            fn (RemoteSongSetItemRecord $item): array => [
                'songExternalId' => $item->songExternalId,
                'sourcePosition' => $item->sourcePosition,
                'transposedKey' => $this->parseKey($item->transposedKey),
                'notes' => $item->notes,
            ],
            $record->items,
        );
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);

        if ($date === false) {
            $this->logger->warning('Ignoring unparseable service date from SongbookPro', ['value' => $value]);

            return null;
        }

        return $date;
    }

    private function parseKey(?string $value): ?MusicalKey
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new MusicalKey($value);
        } catch (InvalidMusicalKeyException $exception) {
            $this->logger->warning('Ignoring invalid musical key from SongbookPro', [
                'value' => $value,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
