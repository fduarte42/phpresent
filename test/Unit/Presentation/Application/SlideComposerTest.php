<?php

declare(strict_types=1);

use Phpresent\Presentation\Application\Service\SlideComposer;
use Phpresent\Presentation\Domain\ValueObject\RenderOptions;
use Phpresent\Presentation\Domain\ValueObject\SlideSourceType;
use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;

function songToCompose(): Song
{
    return new Song(
        externalId: 'sbp-1',
        title: 'Amazing Grace',
        authors: ['John Newton'],
        format: LyricFormat::PlainText,
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
}

it('composes a SlideDeck tagged with the Song source', function (): void {
    $song = songToCompose();
    $song->replaceSections(new SongSection($song, position: 0, type: SectionType::Verse, content: 'Line one'));

    $deck = (new SlideComposer())->compose($song);

    expect($deck->sourceType)->toBe(SlideSourceType::Song);
    expect($deck->sourceId)->toBe('sbp-1');
});

it('never merges two sections into the same slide, even when both are short', function (): void {
    $song = songToCompose();
    $song->replaceSections(
        new SongSection($song, position: 0, type: SectionType::Verse, content: 'Verse line'),
        new SongSection($song, position: 1, type: SectionType::Chorus, content: 'Chorus line'),
    );

    $deck = (new SlideComposer())->compose($song, new RenderOptions(maxLinesPerSlide: 10));

    expect($deck->slides)->toHaveCount(2);
    expect($deck->slides[0]->sectionType)->toBe('verse');
    expect($deck->slides[0]->lines)->toBe(['Verse line']);
    expect($deck->slides[1]->sectionType)->toBe('chorus');
    expect($deck->slides[1]->lines)->toBe(['Chorus line']);
});

it('paginates a section into multiple slides once maxLinesPerSlide is exceeded', function (): void {
    $song = songToCompose();
    $song->replaceSections(new SongSection(
        $song,
        position: 0,
        type: SectionType::Verse,
        content: "Line 1\nLine 2\nLine 3\nLine 4\nLine 5",
    ));

    $deck = (new SlideComposer())->compose($song, new RenderOptions(maxLinesPerSlide: 2));

    expect($deck->slides)->toHaveCount(3);
    expect($deck->slides[0]->lines)->toBe(['Line 1', 'Line 2']);
    expect($deck->slides[1]->lines)->toBe(['Line 3', 'Line 4']);
    expect($deck->slides[2]->lines)->toBe(['Line 5']);
});

it('wraps a line longer than maxCharsPerLine', function (): void {
    $song = songToCompose();
    $song->replaceSections(new SongSection(
        $song,
        position: 0,
        type: SectionType::Verse,
        content: 'This line is definitely longer than twenty characters',
    ));

    $deck = (new SlideComposer())->compose($song, new RenderOptions(maxLinesPerSlide: 10, maxCharsPerLine: 20));

    $allLines = $deck->slides[0]->lines;
    foreach ($allLines as $line) {
        expect(mb_strlen($line))->toBeLessThanOrEqual(20);
    }
    expect(count($allLines))->toBeGreaterThan(1);
});

it('strips chords via SectionRenderer before pagination', function (): void {
    $song = songToCompose();
    $song->replaceSections(new SongSection(
        $song,
        position: 0,
        type: SectionType::Verse,
        content: 'Amazing grace',
        chordProSource: '[G]Amazing [C]grace',
    ));

    $deck = (new SlideComposer())->compose($song);

    expect($deck->slides[0]->lines)->toBe(['Amazing grace']);
});

it('produces no slides for a song with no sections', function (): void {
    $deck = (new SlideComposer())->compose(songToCompose());

    expect($deck->slides)->toBe([]);
    expect($deck->count())->toBe(0);
});
