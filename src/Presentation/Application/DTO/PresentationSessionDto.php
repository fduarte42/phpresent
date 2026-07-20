<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\DTO;

use Phpresent\Presentation\Domain\Entity\PresentationSession;

final readonly class PresentationSessionDto
{
    public function __construct(
        public string $id,
        public ?SlideDeckDto $currentDeck,
        public int $currentSlideIndex,
        public bool $isBlanked,
        public bool $isFrozen,
        public bool $lyricsHidden,
        public int $fontSizeAdjust,
        public ?string $emergencyMessage,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(PresentationSession $session): self
    {
        $deck = $session->currentDeck();

        return new self(
            id: $session->id()->toString(),
            currentDeck: $deck === null ? null : SlideDeckDto::fromValueObject($deck),
            currentSlideIndex: $session->currentSlideIndex(),
            isBlanked: $session->isBlanked(),
            isFrozen: $session->isFrozen(),
            lyricsHidden: $session->lyricsHidden(),
            fontSizeAdjust: $session->fontSizeAdjust(),
            emergencyMessage: $session->emergencyMessage(),
            updatedAt: $session->updatedAt()->format(DATE_ATOM),
        );
    }
}
