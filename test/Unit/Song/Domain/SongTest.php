<?php

declare(strict_types=1);

use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;

function makeSong(): Song
{
    return new Song(
        externalId: 'sbp-123',
        title: 'Amazing Grace',
        authors: ['John Newton'],
        format: LyricFormat::PlainText,
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
}

it('preserves section order exactly as provided, regardless of insertion order', function (): void {
    $song = makeSong();

    $chorus = new SongSection($song, position: 1, type: SectionType::Chorus, content: 'Chorus text');
    $verse = new SongSection($song, position: 0, type: SectionType::Verse, content: 'Verse text');

    // Inserted out of order on purpose — the entity must not "optimize" this.
    $song->replaceSections($chorus, $verse);

    $ordered = $song->sections();

    expect($ordered)->toHaveCount(2);
    expect($ordered[0]->type())->toBe(SectionType::Verse);
    expect(array_map(fn ($s) => $s->position(), $ordered))->toBe([0, 1]);
});

it('detects divergence from a different upstream revision or checksum', function (): void {
    $song = makeSong();

    expect($song->hasDiverged('rev-1', 'checksum-1'))->toBeFalse();
    expect($song->hasDiverged('rev-2', 'checksum-1'))->toBeTrue();
    expect($song->hasDiverged('rev-1', 'checksum-2'))->toBeTrue();
});

it('updates fields in place when synced again', function (): void {
    $song = makeSong();
    $now = new DateTimeImmutable();

    $song->applySync(
        title: 'Amazing Grace (My Chains Are Gone)',
        authors: ['John Newton', 'Chris Tomlin'],
        format: LyricFormat::ChordPro,
        sourceRevision: 'rev-2',
        sourceChecksum: 'checksum-2',
        copyright: '2006 sixsteps Music',
        ccli: null,
        defaultKey: null,
        tempo: 72,
        capo: 2,
        tags: ['hymn'],
        metadata: [],
        now: $now,
    );

    expect($song->title())->toBe('Amazing Grace (My Chains Are Gone)');
    expect($song->authors())->toBe(['John Newton', 'Chris Tomlin']);
    expect($song->format())->toBe(LyricFormat::ChordPro);
    expect($song->tempo())->toBe(72);
    expect($song->hasDiverged('rev-2', 'checksum-2'))->toBeFalse();
});
