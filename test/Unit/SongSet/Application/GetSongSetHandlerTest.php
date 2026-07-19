<?php

declare(strict_types=1);

use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\SongSet\Application\Query\GetSongSetHandler;
use Phpresent\SongSet\Application\Query\GetSongSetQuery;
use Phpresent\SongSet\Domain\Entity\SongSet;
use PhpresentTest\Support\InMemorySongRepository;
use PhpresentTest\Support\InMemorySongSetRepository;

it('resolves each item against the referenced Song for display', function (): void {
    $songRepository = new InMemorySongRepository();
    $songRepository->save(new Song(
        externalId: 'song-a',
        title: 'Amazing Grace',
        authors: [],
        format: LyricFormat::PlainText,
        sourceRevision: 'r1',
        sourceChecksum: 'c1',
    ));

    $songSetRepository = new InMemorySongSetRepository();
    $songSet = new SongSet(
        externalId: 'sbp-set-1',
        name: 'Sunday Morning',
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
    $songSet->syncItems([
        ['songExternalId' => 'song-a', 'sourcePosition' => 0, 'transposedKey' => null, 'notes' => null],
        ['songExternalId' => 'song-unsynced', 'sourcePosition' => 1, 'transposedKey' => null, 'notes' => null],
    ]);
    $songSetRepository->save($songSet);

    $handler = new GetSongSetHandler($songSetRepository, $songRepository);
    $dto = $handler(new GetSongSetQuery($songSet->id()->toString()));

    expect($dto)->not->toBeNull();
    expect($dto->items[0]->songTitle)->toBe('Amazing Grace');
    expect($dto->items[1]->songTitle)->toBeNull();
});

it('returns null for an unknown id', function (): void {
    $handler = new GetSongSetHandler(new InMemorySongSetRepository(), new InMemorySongRepository());

    expect($handler(new GetSongSetQuery(Ramsey\Uuid\Uuid::uuid4()->toString())))->toBeNull();
});

it('returns null for a malformed id', function (): void {
    $handler = new GetSongSetHandler(new InMemorySongSetRepository(), new InMemorySongRepository());

    expect($handler(new GetSongSetQuery('not-a-uuid')))->toBeNull();
});
