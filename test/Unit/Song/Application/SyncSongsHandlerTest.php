<?php

declare(strict_types=1);

use Phpresent\Song\Application\Command\SyncSongsCommand;
use Phpresent\Song\Application\Command\SyncSongsHandler;
use Phpresent\Song\Application\DTO\RemoteSongRecord;
use Phpresent\Song\Application\DTO\RemoteSongSectionRecord;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;
use PhpresentTest\Support\FakeSongSource;
use PhpresentTest\Support\InMemorySongRepository;
use PhpresentTest\Support\InMemorySyncStateRepository;
use Psr\Log\NullLogger;

function remoteSong(string $externalId, string $title, string $revision, string $checksum): RemoteSongRecord
{
    return new RemoteSongRecord(
        externalId: $externalId,
        title: $title,
        authors: ['Author'],
        format: LyricFormat::PlainText,
        revision: $revision,
        checksum: $checksum,
        sections: [
            new RemoteSongSectionRecord(position: 0, type: SectionType::Verse, content: 'Verse 1'),
            new RemoteSongSectionRecord(position: 1, type: SectionType::Chorus, content: 'Chorus'),
        ],
    );
}

it('creates new songs on first sync', function (): void {
    $source = new FakeSongSource([remoteSong('sbp-1', 'Song One', 'rev-1', 'checksum-1')]);
    $repository = new InMemorySongRepository();
    $handler = new SyncSongsHandler($source, $repository, new InMemorySyncStateRepository(), new NullLogger());

    $result = $handler(new SyncSongsCommand());

    expect($result->created)->toBe(1);
    expect($result->updated)->toBe(0);
    expect($repository->count())->toBe(1);

    $song = $repository->findByExternalId('sbp-1');
    expect($song)->not->toBeNull();
    expect($song->sections())->toHaveCount(2);
});

it('does not touch songs whose revision and checksum are unchanged', function (): void {
    $record = remoteSong('sbp-1', 'Song One', 'rev-1', 'checksum-1');
    $source = new FakeSongSource([$record]);
    $repository = new InMemorySongRepository();
    $syncState = new InMemorySyncStateRepository();
    $handler = new SyncSongsHandler($source, $repository, $syncState, new NullLogger());

    $handler(new SyncSongsCommand());
    $result = $handler(new SyncSongsCommand(forceFullSync: true));

    expect($result->created)->toBe(0);
    expect($result->updated)->toBe(0);
    expect($result->unchanged)->toBe(1);
});

it('updates a song whose revision changed upstream', function (): void {
    $repository = new InMemorySongRepository();
    $syncState = new InMemorySyncStateRepository();

    $firstPass = new FakeSongSource([remoteSong('sbp-1', 'Song One', 'rev-1', 'checksum-1')]);
    (new SyncSongsHandler($firstPass, $repository, $syncState, new NullLogger()))(new SyncSongsCommand());

    $secondPass = new FakeSongSource([remoteSong('sbp-1', 'Song One (Updated)', 'rev-2', 'checksum-2')]);
    $result = (new SyncSongsHandler($secondPass, $repository, $syncState, new NullLogger()))(
        new SyncSongsCommand(forceFullSync: true),
    );

    expect($result->updated)->toBe(1);
    expect($repository->findByExternalId('sbp-1')->title())->toBe('Song One (Updated)');
});

it('requests only songs updated since the last successful sync', function (): void {
    $repository = new InMemorySongRepository();
    $syncState = new InMemorySyncStateRepository();

    $firstPass = new FakeSongSource([]);
    (new SyncSongsHandler($firstPass, $repository, $syncState, new NullLogger()))(new SyncSongsCommand());

    $secondSource = new FakeSongSource([]);
    (new SyncSongsHandler($secondSource, $repository, $syncState, new NullLogger()))(new SyncSongsCommand());

    expect($secondSource->lastRequestedSince)->not->toBeNull();
});
