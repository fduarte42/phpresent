<?php

declare(strict_types=1);

use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;
use Phpresent\Song\Infrastructure\Mapper\SongGraphQLMapper;

it('maps a GraphQL song node into a RemoteSongRecord, preserving section order', function (): void {
    $mapper = new SongGraphQLMapper();

    $record = $mapper->mapSong([
        'id' => 'sbp-42',
        'title' => '10,000 Reasons',
        'authors' => ['Matt Redman', 'Jonas Myrin'],
        'copyright' => '2011 Thankyou Music',
        'ccli' => '6016351',
        'key' => 'C',
        'tempo' => 73,
        'capo' => 0,
        'tags' => ['worship', 'CCLI Top 100'],
        'format' => 'chordpro',
        'revision' => 'rev-7',
        'checksum' => 'abc123',
        'sections' => [
            ['position' => 1, 'type' => 'chorus', 'content' => 'Bless the Lord, O my soul'],
            ['position' => 0, 'type' => 'verse', 'label' => 'Verse 1', 'content' => 'The sun comes up'],
        ],
    ]);

    expect($record->externalId)->toBe('sbp-42');
    expect($record->title)->toBe('10,000 Reasons');
    expect($record->authors)->toBe(['Matt Redman', 'Jonas Myrin']);
    expect($record->format)->toBe(LyricFormat::ChordPro);
    expect($record->ccli)->toBe('6016351');
    expect($record->defaultKey)->toBe('C');
    expect($record->sections)->toHaveCount(2);

    // Mapper must preserve SongbookPro's given order verbatim, not re-sort it.
    expect($record->sections[0]->position)->toBe(1);
    expect($record->sections[0]->type)->toBe(SectionType::Chorus);
    expect($record->sections[1]->position)->toBe(0);
    expect($record->sections[1]->type)->toBe(SectionType::Verse);
    expect($record->sections[1]->label)->toBe('Verse 1');
});

it('defaults unrecognized section types to Custom', function (): void {
    $mapper = new SongGraphQLMapper();

    $record = $mapper->mapSong([
        'id' => 'sbp-1',
        'title' => 'Test',
        'format' => 'plain_text',
        'sections' => [
            ['position' => 0, 'type' => 'vamp', 'content' => 'x'],
        ],
    ]);

    expect($record->sections[0]->type)->toBe(SectionType::Custom);
});
