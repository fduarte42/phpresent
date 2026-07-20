<?php

declare(strict_types=1);

use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\Service\SectionRenderer;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;

function songForRendering(): Song
{
    return new Song(
        externalId: 'sbp-1',
        title: 'Test Song',
        authors: [],
        format: LyricFormat::ChordPro,
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
}

it('strips ChordPro chord brackets when chordProSource is present', function (): void {
    $section = new SongSection(
        songForRendering(),
        position: 0,
        type: SectionType::Verse,
        content: 'Amazing grace how sweet the sound',
        chordProSource: "[G]Amazing grace how [C]sweet the sound\n[D]That saved a wretch [G]like me",
    );

    $rendered = SectionRenderer::render($section);

    expect($rendered->lines)->toBe([
        'Amazing grace how sweet the sound',
        'That saved a wretch like me',
    ]);
});

it('passes plain content through unchanged when there is no chordProSource', function (): void {
    $section = new SongSection(
        songForRendering(),
        position: 0,
        type: SectionType::Verse,
        content: "Line one\nLine two",
    );

    $rendered = SectionRenderer::render($section);

    expect($rendered->lines)->toBe(['Line one', 'Line two']);
});

it('returns an empty lines array for empty content', function (): void {
    $section = new SongSection(songForRendering(), position: 0, type: SectionType::Verse, content: '');

    expect(SectionRenderer::render($section)->lines)->toBe(['']);
});
