<?php

declare(strict_types=1);

use Phpresent\SongSet\Infrastructure\Mapper\SongSetGraphQLMapper;

it('maps a GraphQL song set node into a RemoteSongSetRecord, preserving item order', function (): void {
    $mapper = new SongSetGraphQLMapper();

    $record = $mapper->mapSongSet([
        'id' => 'sbp-set-42',
        'name' => 'Sunday Morning',
        'serviceDate' => '2026-07-19T00:00:00+00:00',
        'notes' => 'Communion Sunday',
        'revision' => 'rev-7',
        'checksum' => 'abc123',
        'items' => [
            ['songId' => 'sbp-2', 'position' => 1, 'transposedKey' => 'D'],
            ['songId' => 'sbp-1', 'position' => 0, 'notes' => 'Start soft'],
        ],
    ]);

    expect($record->externalId)->toBe('sbp-set-42');
    expect($record->name)->toBe('Sunday Morning');
    expect($record->serviceDate)->toBe('2026-07-19T00:00:00+00:00');
    expect($record->notes)->toBe('Communion Sunday');
    expect($record->items)->toHaveCount(2);

    // Mapper must preserve SongbookPro's given order verbatim, not re-sort it.
    expect($record->items[0]->songExternalId)->toBe('sbp-2');
    expect($record->items[0]->sourcePosition)->toBe(1);
    expect($record->items[0]->transposedKey)->toBe('D');
    expect($record->items[1]->songExternalId)->toBe('sbp-1');
    expect($record->items[1]->sourcePosition)->toBe(0);
    expect($record->items[1]->notes)->toBe('Start soft');
});

it('defaults optional fields to null', function (): void {
    $mapper = new SongSetGraphQLMapper();

    $record = $mapper->mapSongSet([
        'id' => 'sbp-set-1',
        'name' => 'Test',
        'items' => [
            ['songId' => 'sbp-1', 'position' => 0],
        ],
    ]);

    expect($record->serviceDate)->toBeNull();
    expect($record->notes)->toBeNull();
    expect($record->items[0]->transposedKey)->toBeNull();
    expect($record->items[0]->notes)->toBeNull();
});
