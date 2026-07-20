<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\Service;

use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\ValueObject\RenderedSectionText;

/**
 * Extracts plain, chord-free display lines from a `SongSection`, per
 * format (SDD §4: "format is preserved, not normalized" — this reads the
 * stored format without transcoding it into anything else).
 *
 * A section is treated as ChordPro when it carries a `chordProSource`
 * (regardless of the parent `Song::format`, since `chordProSource` is the
 * authoritative signal that this specific section's text has bracketed
 * chords in it) — that keeps this pure function self-contained on
 * `SongSection` alone, matching its SDD §4 signature, instead of also
 * needing the parent `Song`'s format passed in separately.
 */
final class SectionRenderer
{
    public static function render(SongSection $section): RenderedSectionText
    {
        $source = $section->chordProSource() ?? $section->content();
        $text = $section->chordProSource() !== null ? self::stripChords($source) : $source;

        $lines = preg_split('/\R/', $text);

        return new RenderedSectionText($lines === false ? [] : $lines);
    }

    private static function stripChords(string $chordPro): string
    {
        return (string) preg_replace('/\[[^\]]*\]/', '', $chordPro);
    }
}
