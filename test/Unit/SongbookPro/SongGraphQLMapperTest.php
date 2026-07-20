<?php

declare(strict_types=1);

use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Infrastructure\Mapper\SongGraphQLMapper;
use Phpresent\SongbookPro\Infrastructure\GraphQL\LibraryItem;

it('maps a SONG LibraryItem into a RemoteSongRecord, passing unmodeled fields through as metadata', function (): void {
    $mapper = new SongGraphQLMapper();

    $item = LibraryItem::fromGraphQL([
        'id' => 'sbp-42',
        'type' => 'SONG',
        'deleted' => false,
        'data' => json_encode([
            'title' => '10,000 Reasons',
            'artist' => 'Matt Redman, Jonas Myrin',
            'tempo' => 73,
            'subtitle' => '',
        ], JSON_THROW_ON_ERROR),
    ]);

    $record = $mapper->mapSong($item);

    expect($record)->not->toBeNull();
    expect($record->externalId)->toBe('sbp-42');
    expect($record->title)->toBe('10,000 Reasons');
    expect($record->authors)->toBe(['Matt Redman', 'Jonas Myrin']);
    expect($record->tempo)->toBe(73);
    expect($record->format)->toBe(LyricFormat::PlainText);
    expect($record->sections)->toBe([]);
    expect($record->revision)->toBe($record->checksum);
    expect($record->metadata)->toBe([
        'title' => '10,000 Reasons',
        'artist' => 'Matt Redman, Jonas Myrin',
        'tempo' => 73,
        'subtitle' => '',
    ]);
});

it('produces the same checksum for identical data and a different one when data changes', function (): void {
    $mapper = new SongGraphQLMapper();

    $a = $mapper->mapSong(LibraryItem::fromGraphQL([
        'id' => 'sbp-1',
        'type' => 'SONG',
        'deleted' => false,
        'data' => json_encode(['title' => 'Test'], JSON_THROW_ON_ERROR),
    ]));
    $b = $mapper->mapSong(LibraryItem::fromGraphQL([
        'id' => 'sbp-1',
        'type' => 'SONG',
        'deleted' => false,
        'data' => json_encode(['title' => 'Test (renamed)'], JSON_THROW_ON_ERROR),
    ]));

    expect($a->checksum)->not->toBe($b->checksum);
});

it('returns null for a tombstoned item', function (): void {
    $mapper = new SongGraphQLMapper();

    $item = LibraryItem::fromGraphQL(['id' => 'sbp-1', 'type' => 'SONG', 'deleted' => true]);

    expect($mapper->mapSong($item))->toBeNull();
});

it('defaults missing title and artist to empty', function (): void {
    $mapper = new SongGraphQLMapper();

    $item = LibraryItem::fromGraphQL([
        'id' => 'sbp-1',
        'type' => 'SONG',
        'deleted' => false,
        'data' => json_encode([], JSON_THROW_ON_ERROR),
    ]);

    $record = $mapper->mapSong($item);

    expect($record->title)->toBe('');
    expect($record->authors)->toBe([]);
    expect($record->tempo)->toBeNull();
});
