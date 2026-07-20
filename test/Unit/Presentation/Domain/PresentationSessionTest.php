<?php

declare(strict_types=1);

use Phpresent\Presentation\Domain\Entity\PresentationSession;
use Phpresent\Presentation\Domain\Exception\InvalidSlideIndexException;
use Phpresent\Presentation\Domain\ValueObject\Slide;
use Phpresent\Presentation\Domain\ValueObject\SlideDeck;
use Phpresent\Presentation\Domain\ValueObject\SlideSourceType;

function threeSlideDeck(): SlideDeck
{
    return new SlideDeck(
        sourceType: SlideSourceType::Song,
        sourceId: 'sbp-1',
        slides: [
            new Slide(['Line 1']),
            new Slide(['Line 2']),
            new Slide(['Line 3']),
        ],
    );
}

it('starts with no deck and every control flag off', function (): void {
    $session = new PresentationSession();

    expect($session->currentDeck())->toBeNull();
    expect($session->currentSlideIndex())->toBe(0);
    expect($session->isBlanked())->toBeFalse();
    expect($session->isFrozen())->toBeFalse();
    expect($session->lyricsHidden())->toBeFalse();
    expect($session->fontSizeAdjust())->toBe(0);
    expect($session->emergencyMessage())->toBeNull();
});

it('resets the slide index to 0 when a new deck is loaded', function (): void {
    $session = new PresentationSession();
    $now = new DateTimeImmutable();

    $session->loadDeck(threeSlideDeck(), $now);
    $session->jumpToSlide(2, $now);
    expect($session->currentSlideIndex())->toBe(2);

    $session->loadDeck(threeSlideDeck(), $now);
    expect($session->currentSlideIndex())->toBe(0);
});

it('advances and retreats through slides, clamped to the deck bounds', function (): void {
    $session = new PresentationSession();
    $now = new DateTimeImmutable();
    $session->loadDeck(threeSlideDeck(), $now);

    $session->previous($now);
    expect($session->currentSlideIndex())->toBe(0); // clamped, no exception

    $session->next($now);
    $session->next($now);
    $session->next($now);
    expect($session->currentSlideIndex())->toBe(2); // clamped at last slide
});

it('throws when jumping to an out-of-range slide index', function (): void {
    $session = new PresentationSession();
    $now = new DateTimeImmutable();
    $session->loadDeck(threeSlideDeck(), $now);

    expect(fn () => $session->jumpToSlide(3, $now))->toThrow(InvalidSlideIndexException::class);
    expect(fn () => $session->jumpToSlide(-1, $now))->toThrow(InvalidSlideIndexException::class);
});

it('allows next()/previous() to no-op at index 0 when there is no deck', function (): void {
    $session = new PresentationSession();
    $now = new DateTimeImmutable();

    $session->next($now);
    $session->previous($now);

    expect($session->currentSlideIndex())->toBe(0);
});

it('toggles blank, freeze, lyrics-hidden, font size and emergency message independently', function (): void {
    $session = new PresentationSession();
    $now = new DateTimeImmutable();

    $session->setBlanked(true, $now);
    $session->setFrozen(true, $now);
    $session->setLyricsHidden(true, $now);
    $session->setFontSizeAdjust(2, $now);
    $session->setEmergencyMessage('Evacuate calmly', $now);

    expect($session->isBlanked())->toBeTrue();
    expect($session->isFrozen())->toBeTrue();
    expect($session->lyricsHidden())->toBeTrue();
    expect($session->fontSizeAdjust())->toBe(2);
    expect($session->emergencyMessage())->toBe('Evacuate calmly');

    $session->setEmergencyMessage(null, $now);
    expect($session->emergencyMessage())->toBeNull();
});
