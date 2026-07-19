<?php

declare(strict_types=1);

use Phpresent\SongSet\Domain\Entity\SongSet;
use Phpresent\SongSet\Domain\Exception\UnknownSongSetItemException;

function makeSongSet(): SongSet
{
    return new SongSet(
        externalId: 'sbp-set-1',
        name: 'Sunday Morning',
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
}

/**
 * @param list<array{songExternalId: string, sourcePosition: int}> $items
 */
function syncItems(SongSet $songSet, array $items): void
{
    $songSet->syncItems(array_map(
        static fn (array $item): array => [
            'songExternalId' => $item['songExternalId'],
            'sourcePosition' => $item['sourcePosition'],
            'transposedKey' => null,
            'notes' => null,
        ],
        $items,
    ));
}

it('orders items by source position when there is no local override', function (): void {
    $songSet = makeSongSet();
    syncItems($songSet, [
        ['songExternalId' => 'song-b', 'sourcePosition' => 1],
        ['songExternalId' => 'song-a', 'sourcePosition' => 0],
    ]);

    $ordered = $songSet->items();

    expect(array_map(fn ($item) => $item->songExternalId(), $ordered))->toBe(['song-a', 'song-b']);
});

it('orders items by local override when set', function (): void {
    $songSet = makeSongSet();
    syncItems($songSet, [
        ['songExternalId' => 'song-a', 'sourcePosition' => 0],
        ['songExternalId' => 'song-b', 'sourcePosition' => 1],
    ]);

    $ids = array_map(fn ($item) => $item->id()->toString(), $songSet->items());
    $songSet->reorder(array_reverse($ids));

    $ordered = $songSet->items();
    expect(array_map(fn ($item) => $item->songExternalId(), $ordered))->toBe(['song-b', 'song-a']);
});

it('throws when reordering with an unknown item id', function (): void {
    $songSet = makeSongSet();
    syncItems($songSet, [['songExternalId' => 'song-a', 'sourcePosition' => 0]]);

    $songSet->reorder(['not-a-real-item-id']);
})->throws(UnknownSongSetItemException::class);

it('preserves a local override when a resync leaves source position unchanged', function (): void {
    $songSet = makeSongSet();
    syncItems($songSet, [
        ['songExternalId' => 'song-a', 'sourcePosition' => 0],
        ['songExternalId' => 'song-b', 'sourcePosition' => 1],
    ]);

    $ids = array_map(fn ($item) => $item->id()->toString(), $songSet->items());
    $songSet->reorder(array_reverse($ids));

    // Resync with the exact same source positions.
    syncItems($songSet, [
        ['songExternalId' => 'song-a', 'sourcePosition' => 0],
        ['songExternalId' => 'song-b', 'sourcePosition' => 1],
    ]);

    $ordered = $songSet->items();
    expect(array_map(fn ($item) => $item->songExternalId(), $ordered))->toBe(['song-b', 'song-a']);
    expect($ordered[0]->localPosition())->not->toBeNull();
});

it('drops a stale local override when the upstream order actually changes', function (): void {
    $songSet = makeSongSet();
    syncItems($songSet, [
        ['songExternalId' => 'song-a', 'sourcePosition' => 0],
        ['songExternalId' => 'song-b', 'sourcePosition' => 1],
    ]);

    $ids = array_map(fn ($item) => $item->id()->toString(), $songSet->items());
    $songSet->reorder(array_reverse($ids));

    // Upstream swapped the order for real.
    syncItems($songSet, [
        ['songExternalId' => 'song-a', 'sourcePosition' => 1],
        ['songExternalId' => 'song-b', 'sourcePosition' => 0],
    ]);

    $ordered = $songSet->items();
    expect(array_map(fn ($item) => $item->songExternalId(), $ordered))->toBe(['song-b', 'song-a']);
    expect($ordered[0]->localPosition())->toBeNull();
    expect($ordered[1]->localPosition())->toBeNull();
});

it('removes items no longer present upstream and adds new ones', function (): void {
    $songSet = makeSongSet();
    syncItems($songSet, [
        ['songExternalId' => 'song-a', 'sourcePosition' => 0],
        ['songExternalId' => 'song-b', 'sourcePosition' => 1],
    ]);

    syncItems($songSet, [
        ['songExternalId' => 'song-a', 'sourcePosition' => 0],
        ['songExternalId' => 'song-c', 'sourcePosition' => 1],
    ]);

    $ordered = array_map(fn ($item) => $item->songExternalId(), $songSet->items());
    expect($ordered)->toBe(['song-a', 'song-c']);
});

it('detects divergence from a different upstream revision or checksum', function (): void {
    $songSet = makeSongSet();

    expect($songSet->hasDiverged('rev-1', 'checksum-1'))->toBeFalse();
    expect($songSet->hasDiverged('rev-2', 'checksum-1'))->toBeTrue();
    expect($songSet->hasDiverged('rev-1', 'checksum-2'))->toBeTrue();
});

it('updates fields in place when synced again', function (): void {
    $songSet = makeSongSet();
    $now = new DateTimeImmutable();

    $songSet->applySync(
        name: 'Sunday Morning (Updated)',
        sourceRevision: 'rev-2',
        sourceChecksum: 'checksum-2',
        serviceDate: $now,
        notes: 'Communion Sunday',
        now: $now,
    );

    expect($songSet->name())->toBe('Sunday Morning (Updated)');
    expect($songSet->notes())->toBe('Communion Sunday');
    expect($songSet->hasDiverged('rev-2', 'checksum-2'))->toBeFalse();
});
