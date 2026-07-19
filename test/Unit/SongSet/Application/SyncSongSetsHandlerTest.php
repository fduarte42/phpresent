<?php

declare(strict_types=1);

use Phpresent\SongSet\Application\Command\SyncSongSetsCommand;
use Phpresent\SongSet\Application\Command\SyncSongSetsHandler;
use Phpresent\SongSet\Application\DTO\RemoteSongSetItemRecord;
use Phpresent\SongSet\Application\DTO\RemoteSongSetRecord;
use PhpresentTest\Support\FakeSongSetSource;
use PhpresentTest\Support\InMemorySongSetRepository;
use PhpresentTest\Support\InMemorySyncStateRepository;
use Psr\Log\NullLogger;

function remoteSongSet(string $externalId, string $name, string $revision, string $checksum): RemoteSongSetRecord
{
    return new RemoteSongSetRecord(
        externalId: $externalId,
        name: $name,
        revision: $revision,
        checksum: $checksum,
        items: [
            new RemoteSongSetItemRecord(songExternalId: 'song-a', sourcePosition: 0),
            new RemoteSongSetItemRecord(songExternalId: 'song-b', sourcePosition: 1),
        ],
    );
}

it('creates new song sets on first sync', function (): void {
    $source = new FakeSongSetSource([remoteSongSet('sbp-set-1', 'Sunday Morning', 'rev-1', 'checksum-1')]);
    $repository = new InMemorySongSetRepository();
    $handler = new SyncSongSetsHandler($source, $repository, new InMemorySyncStateRepository(), new NullLogger());

    $result = $handler(new SyncSongSetsCommand());

    expect($result->created)->toBe(1);
    expect($result->updated)->toBe(0);
    expect($repository->count())->toBe(1);

    $songSet = $repository->findByExternalId('sbp-set-1');
    expect($songSet)->not->toBeNull();
    expect($songSet->items())->toHaveCount(2);
});

it('does not touch song sets whose revision and checksum are unchanged', function (): void {
    $record = remoteSongSet('sbp-set-1', 'Sunday Morning', 'rev-1', 'checksum-1');
    $source = new FakeSongSetSource([$record]);
    $repository = new InMemorySongSetRepository();
    $syncState = new InMemorySyncStateRepository();
    $handler = new SyncSongSetsHandler($source, $repository, $syncState, new NullLogger());

    $handler(new SyncSongSetsCommand());
    $result = $handler(new SyncSongSetsCommand(forceFullSync: true));

    expect($result->created)->toBe(0);
    expect($result->updated)->toBe(0);
    expect($result->unchanged)->toBe(1);
});

it('updates a song set whose revision changed upstream', function (): void {
    $repository = new InMemorySongSetRepository();
    $syncState = new InMemorySyncStateRepository();

    $firstPass = new FakeSongSetSource([remoteSongSet('sbp-set-1', 'Sunday Morning', 'rev-1', 'checksum-1')]);
    (new SyncSongSetsHandler($firstPass, $repository, $syncState, new NullLogger()))(new SyncSongSetsCommand());

    $secondPass = new FakeSongSetSource([remoteSongSet('sbp-set-1', 'Sunday Morning (Updated)', 'rev-2', 'checksum-2')]);
    $result = (new SyncSongSetsHandler($secondPass, $repository, $syncState, new NullLogger()))(
        new SyncSongSetsCommand(forceFullSync: true),
    );

    expect($result->updated)->toBe(1);
    expect($repository->findByExternalId('sbp-set-1')->name())->toBe('Sunday Morning (Updated)');
});

it('requests only song sets updated since the last successful sync', function (): void {
    $repository = new InMemorySongSetRepository();
    $syncState = new InMemorySyncStateRepository();

    $firstPass = new FakeSongSetSource([]);
    (new SyncSongSetsHandler($firstPass, $repository, $syncState, new NullLogger()))(new SyncSongSetsCommand());

    $secondSource = new FakeSongSetSource([]);
    (new SyncSongSetsHandler($secondSource, $repository, $syncState, new NullLogger()))(new SyncSongSetsCommand());

    expect($secondSource->lastRequestedSince)->not->toBeNull();
});

it('preserves a local reorder override across a sync pass with unchanged source order', function (): void {
    $repository = new InMemorySongSetRepository();
    $syncState = new InMemorySyncStateRepository();

    $source = new FakeSongSetSource([remoteSongSet('sbp-set-1', 'Sunday Morning', 'rev-1', 'checksum-1')]);
    (new SyncSongSetsHandler($source, $repository, $syncState, new NullLogger()))(new SyncSongSetsCommand());

    $songSet = $repository->findByExternalId('sbp-set-1');
    $ids = array_map(fn ($item) => $item->id()->toString(), $songSet->items());
    $songSet->reorder(array_reverse($ids));
    $repository->save($songSet);

    $secondSource = new FakeSongSetSource([remoteSongSet('sbp-set-1', 'Sunday Morning', 'rev-2', 'checksum-2')]);
    (new SyncSongSetsHandler($secondSource, $repository, $syncState, new NullLogger()))(
        new SyncSongSetsCommand(forceFullSync: true),
    );

    $reloaded = $repository->findByExternalId('sbp-set-1');
    $order = array_map(fn ($item) => $item->songExternalId(), $reloaded->items());
    expect($order)->toBe(['song-b', 'song-a']);
});
