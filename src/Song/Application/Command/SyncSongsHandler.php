<?php

declare(strict_types=1);

namespace Phpresent\Song\Application\Command;

use DateTimeImmutable;
use Phpresent\Shared\Domain\Repository\SyncStateRepositoryInterface;
use Phpresent\Song\Application\DTO\RemoteSongRecord;
use Phpresent\Song\Application\Service\SongSourceInterface;
use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\Exception\InvalidCcliNumberException;
use Phpresent\Song\Domain\Exception\InvalidMusicalKeyException;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Phpresent\Song\Domain\ValueObject\CcliNumber;
use Phpresent\Song\Domain\ValueObject\MusicalKey;
use Psr\Log\LoggerInterface;

final readonly class SyncSongsHandler
{
    private const string ENTITY_TYPE = 'song';

    public function __construct(
        private SongSourceInterface $songSource,
        private SongRepositoryInterface $songRepository,
        private SyncStateRepositoryInterface $syncState,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncSongsCommand $command): SyncSongsResult
    {
        $since = $command->forceFullSync
            ? null
            : $this->syncState->getLastSyncedAt(self::ENTITY_TYPE);

        $syncStartedAt = new DateTimeImmutable();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($this->songSource->fetchAll($since?->format(DATE_ATOM)) as $record) {
            $result = $this->syncOne($record, $syncStartedAt);
            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default => $unchanged++,
            };
        }

        $lastSyncedAt = $this->songSource->lastSyncedAt();
        $this->syncState->setLastSyncedAt(
            self::ENTITY_TYPE,
            $lastSyncedAt !== null ? new DateTimeImmutable($lastSyncedAt) : $syncStartedAt,
        );

        $this->logger->info('SongbookPro song sync complete', [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
        ]);

        return new SyncSongsResult($created, $updated, $unchanged);
    }

    private function syncOne(RemoteSongRecord $record, DateTimeImmutable $now): string
    {
        $ccli = $this->parseCcli($record->ccli);
        $defaultKey = $this->parseKey($record->defaultKey);

        $song = $this->songRepository->findByExternalId($record->externalId);

        if ($song === null) {
            $song = new Song(
                externalId: $record->externalId,
                title: $record->title,
                authors: $record->authors,
                format: $record->format,
                sourceRevision: $record->revision,
                sourceChecksum: $record->checksum,
                copyright: $record->copyright,
                ccli: $ccli,
                defaultKey: $defaultKey,
                tempo: $record->tempo,
                capo: $record->capo,
                tags: $record->tags,
                metadata: $record->metadata,
                now: $now,
            );
            $song->replaceSections(...$this->buildSections($song, $record));
            $this->songRepository->save($song);

            return 'created';
        }

        if (!$song->hasDiverged($record->revision, $record->checksum)) {
            return 'unchanged';
        }

        $song->applySync(
            title: $record->title,
            authors: $record->authors,
            format: $record->format,
            sourceRevision: $record->revision,
            sourceChecksum: $record->checksum,
            copyright: $record->copyright,
            ccli: $ccli,
            defaultKey: $defaultKey,
            tempo: $record->tempo,
            capo: $record->capo,
            tags: $record->tags,
            metadata: $record->metadata,
            now: $now,
        );
        $song->replaceSections(...$this->buildSections($song, $record));
        $this->songRepository->save($song);

        return 'updated';
    }

    /**
     * @return list<SongSection>
     */
    private function buildSections(Song $song, RemoteSongRecord $record): array
    {
        return array_map(
            static fn ($section) => new SongSection(
                song: $song,
                position: $section->position,
                type: $section->type,
                content: $section->content,
                label: $section->label,
                chordProSource: $section->chordProSource,
            ),
            $record->sections,
        );
    }

    private function parseCcli(?string $value): ?CcliNumber
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new CcliNumber($value);
        } catch (InvalidCcliNumberException $exception) {
            $this->logger->warning('Ignoring invalid CCLI number from SongbookPro', [
                'value' => $value,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
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
