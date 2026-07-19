<?php

declare(strict_types=1);

use Phpresent\SongSet\Application\Command\ReorderSongSetItemsCommand;
use Phpresent\SongSet\Application\Command\ReorderSongSetItemsHandler;
use Phpresent\SongSet\Application\Query\GetSongSetHandler;
use Phpresent\SongSet\Domain\Entity\SongSet;
use Phpresent\SongSet\Domain\Exception\UnknownSongSetItemException;
use PhpresentTest\Support\InMemorySongRepository;
use PhpresentTest\Support\InMemorySongSetRepository;

function makePersistedSongSet(InMemorySongSetRepository $repository): SongSet
{
    $songSet = new SongSet(
        externalId: 'sbp-set-1',
        name: 'Sunday Morning',
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
    $songSet->syncItems([
        ['songExternalId' => 'song-a', 'sourcePosition' => 0, 'transposedKey' => null, 'notes' => null],
        ['songExternalId' => 'song-b', 'sourcePosition' => 1, 'transposedKey' => null, 'notes' => null],
    ]);
    $repository->save($songSet);

    return $songSet;
}

it('persists a local reorder and returns the updated set', function (): void {
    $songSetRepository = new InMemorySongSetRepository();
    $songSet = makePersistedSongSet($songSetRepository);
    $handler = new ReorderSongSetItemsHandler(
        $songSetRepository,
        new GetSongSetHandler($songSetRepository, new InMemorySongRepository()),
    );

    $ids = array_map(fn ($item) => $item->id()->toString(), $songSet->items());
    $dto = $handler(new ReorderSongSetItemsCommand($songSet->id()->toString(), array_reverse($ids)));

    expect($dto)->not->toBeNull();
    expect(array_map(fn ($item) => $item->songExternalId, $dto->items))->toBe(['song-b', 'song-a']);
});

it('returns null for an unknown song set id', function (): void {
    $songSetRepository = new InMemorySongSetRepository();
    $handler = new ReorderSongSetItemsHandler(
        $songSetRepository,
        new GetSongSetHandler($songSetRepository, new InMemorySongRepository()),
    );

    $dto = $handler(new ReorderSongSetItemsCommand(Ramsey\Uuid\Uuid::uuid4()->toString(), []));

    expect($dto)->toBeNull();
});

it('throws when an ordered id does not belong to the set', function (): void {
    $songSetRepository = new InMemorySongSetRepository();
    $songSet = makePersistedSongSet($songSetRepository);
    $handler = new ReorderSongSetItemsHandler(
        $songSetRepository,
        new GetSongSetHandler($songSetRepository, new InMemorySongRepository()),
    );

    $handler(new ReorderSongSetItemsCommand($songSet->id()->toString(), ['not-a-real-item-id']));
})->throws(UnknownSongSetItemException::class);
