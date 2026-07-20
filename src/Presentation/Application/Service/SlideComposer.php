<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Service;

use Phpresent\Presentation\Domain\ValueObject\RenderOptions;
use Phpresent\Presentation\Domain\ValueObject\Slide;
use Phpresent\Presentation\Domain\ValueObject\SlideDeck;
use Phpresent\Presentation\Domain\ValueObject\SlideSourceType;
use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\Service\SectionRenderer;

/**
 * Composes a `Song` into a `SlideDeck` (SDD §7): a deterministic, pure
 * function of (content, rules) — no I/O, so it's unit-testable without a
 * database or GraphQL server.
 *
 * Depending on `Song\Domain\Entity\Song` from this Application layer
 * mirrors the existing precedent of `SongSet\Application\Query\
 * GetSongSetHandler` depending on `Song\Domain\Repository\
 * SongRepositoryInterface` — cross-module Domain dependencies are fine at
 * the Application layer; what SDD §17.1 forbids is a cross-module
 * Doctrine/Domain-VO coupling *inside* a module's own Domain layer (see
 * `Slide`'s docblock).
 */
final class SlideComposer
{
    public function compose(Song $song, RenderOptions $options = new RenderOptions()): SlideDeck
    {
        $slides = [];

        foreach ($song->sections() as $section) {
            array_push($slides, ...$this->composeSection($section, $options));
        }

        return new SlideDeck(
            sourceType: SlideSourceType::Song,
            sourceId: $song->externalId(),
            slides: $slides,
        );
    }

    /**
     * @return list<Slide>
     */
    private function composeSection(SongSection $section, RenderOptions $options): array
    {
        $wrappedLines = [];

        foreach (SectionRenderer::render($section)->lines as $line) {
            array_push($wrappedLines, ...explode("\n", wordwrap($line, $options->maxCharsPerLine, "\n", true)));
        }

        $slides = [];

        foreach (array_chunk($wrappedLines, max($options->maxLinesPerSlide, 1)) as $chunk) {
            if ($this->isBlank($chunk)) {
                continue;
            }

            $slides[] = new Slide(
                lines: $chunk,
                sectionType: $section->type()->value,
                sectionLabel: $section->label(),
            );
        }

        return $slides;
    }

    /**
     * @param string[] $lines
     */
    private function isBlank(array $lines): bool
    {
        return array_filter($lines, static fn (string $line): bool => trim($line) !== '') === [];
    }
}
