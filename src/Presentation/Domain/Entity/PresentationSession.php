<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phpresent\Presentation\Domain\Exception\InvalidSlideIndexException;
use Phpresent\Presentation\Domain\ValueObject\SlideDeck;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * The one live-output pipeline this install controls (SDD §7) — there is
 * exactly one row in practice, created on first access by
 * `PresentationSessionRepositoryInterface::current()`, but modeled as a
 * normal aggregate (not a config singleton) so it stays testable like
 * everything else in this codebase.
 */
#[ORM\Entity]
#[ORM\Table(name: 'presentation_sessions')]
class PresentationSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'current_deck', type: 'json', nullable: true)]
    private ?array $currentDeck = null;

    #[ORM\Column(name: 'current_slide_index', type: 'integer')]
    private int $currentSlideIndex = 0;

    #[ORM\Column(name: 'is_blanked', type: 'boolean')]
    private bool $isBlanked = false;

    #[ORM\Column(name: 'is_frozen', type: 'boolean')]
    private bool $isFrozen = false;

    #[ORM\Column(name: 'lyrics_hidden', type: 'boolean')]
    private bool $lyricsHidden = false;

    #[ORM\Column(name: 'font_size_adjust', type: 'integer')]
    private int $fontSizeAdjust = 0;

    #[ORM\Column(name: 'emergency_message', type: 'text', nullable: true)]
    private ?string $emergencyMessage = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->id = Uuid::uuid4();
        $this->updatedAt = $now ?? new DateTimeImmutable();
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function currentDeck(): ?SlideDeck
    {
        return $this->currentDeck === null ? null : SlideDeck::fromArray($this->currentDeck);
    }

    public function currentSlideIndex(): int
    {
        return $this->currentSlideIndex;
    }

    public function isBlanked(): bool
    {
        return $this->isBlanked;
    }

    public function isFrozen(): bool
    {
        return $this->isFrozen;
    }

    public function lyricsHidden(): bool
    {
        return $this->lyricsHidden;
    }

    public function fontSizeAdjust(): int
    {
        return $this->fontSizeAdjust;
    }

    public function emergencyMessage(): ?string
    {
        return $this->emergencyMessage;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function loadDeck(SlideDeck $deck, DateTimeImmutable $now): void
    {
        $this->currentDeck = $deck->toArray();
        $this->currentSlideIndex = 0;
        $this->updatedAt = $now;
    }

    public function next(DateTimeImmutable $now): void
    {
        $this->jumpToSlide(min($this->currentSlideIndex + 1, $this->lastSlideIndex()), $now);
    }

    public function previous(DateTimeImmutable $now): void
    {
        $this->jumpToSlide(max($this->currentSlideIndex - 1, 0), $now);
    }

    public function jumpToSlide(int $index, DateTimeImmutable $now): void
    {
        $slideCount = $this->currentDeck()?->count() ?? 0;

        if ($index < 0 || $index >= max($slideCount, 1)) {
            throw InvalidSlideIndexException::forIndex($index, $slideCount);
        }

        $this->currentSlideIndex = $index;
        $this->updatedAt = $now;
    }

    public function setBlanked(bool $blanked, DateTimeImmutable $now): void
    {
        $this->isBlanked = $blanked;
        $this->updatedAt = $now;
    }

    public function setFrozen(bool $frozen, DateTimeImmutable $now): void
    {
        $this->isFrozen = $frozen;
        $this->updatedAt = $now;
    }

    public function setLyricsHidden(bool $hidden, DateTimeImmutable $now): void
    {
        $this->lyricsHidden = $hidden;
        $this->updatedAt = $now;
    }

    public function setFontSizeAdjust(int $steps, DateTimeImmutable $now): void
    {
        $this->fontSizeAdjust = $steps;
        $this->updatedAt = $now;
    }

    public function setEmergencyMessage(?string $message, DateTimeImmutable $now): void
    {
        $this->emergencyMessage = $message;
        $this->updatedAt = $now;
    }

    private function lastSlideIndex(): int
    {
        $slideCount = $this->currentDeck()?->count() ?? 0;

        return max($slideCount - 1, 0);
    }
}
