<?php

declare(strict_types=1);

use Phpresent\Presentation\Application\Command\JumpToSlideCommand;
use Phpresent\Presentation\Application\Command\JumpToSlideHandler;
use Phpresent\Presentation\Application\Command\LoadSongIntoPresentationCommand;
use Phpresent\Presentation\Application\Command\LoadSongIntoPresentationHandler;
use Phpresent\Presentation\Application\Command\NextSlideCommand;
use Phpresent\Presentation\Application\Command\NextSlideHandler;
use Phpresent\Presentation\Application\Command\SetBlankedCommand;
use Phpresent\Presentation\Application\Command\SetBlankedHandler;
use Phpresent\Presentation\Application\Command\SetEmergencyMessageCommand;
use Phpresent\Presentation\Application\Command\SetEmergencyMessageHandler;
use Phpresent\Presentation\Application\Service\SlideComposer;
use Phpresent\Presentation\Domain\Exception\InvalidSlideIndexException;
use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;
use PhpresentTest\Support\InMemoryPresentationSessionRepository;
use PhpresentTest\Support\InMemorySongRepository;

it('loads a song into the session as a composed deck', function (): void {
    $songRepository = new InMemorySongRepository();
    $song = new Song(
        externalId: 'sbp-1',
        title: 'Amazing Grace',
        authors: [],
        format: LyricFormat::PlainText,
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
    $song->replaceSections(new SongSection($song, position: 0, type: SectionType::Verse, content: 'Line one'));
    $songRepository->save($song);

    $sessionRepository = new InMemoryPresentationSessionRepository();
    $handler = new LoadSongIntoPresentationHandler($songRepository, new SlideComposer(), $sessionRepository);

    $dto = $handler(new LoadSongIntoPresentationCommand($song->id()->toString()));

    expect($dto)->not->toBeNull();
    expect($dto->currentDeck)->not->toBeNull();
    expect($dto->currentDeck->sourceId)->toBe('sbp-1');
    expect($dto->currentSlideIndex)->toBe(0);
});

it('returns null when loading an unknown song', function (): void {
    $handler = new LoadSongIntoPresentationHandler(
        new InMemorySongRepository(),
        new SlideComposer(),
        new InMemoryPresentationSessionRepository(),
    );

    expect($handler(new LoadSongIntoPresentationCommand('11111111-1111-1111-1111-111111111111')))->toBeNull();
});

it('advances the slide index via NextSlideHandler against the shared session', function (): void {
    $songRepository = new InMemorySongRepository();
    $song = new Song(
        externalId: 'sbp-1',
        title: 'Song',
        authors: [],
        format: LyricFormat::PlainText,
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
    $song->replaceSections(
        new SongSection($song, position: 0, type: SectionType::Verse, content: 'A'),
        new SongSection($song, position: 1, type: SectionType::Chorus, content: 'B'),
    );
    $songRepository->save($song);

    $sessionRepository = new InMemoryPresentationSessionRepository();
    (new LoadSongIntoPresentationHandler($songRepository, new SlideComposer(), $sessionRepository))(
        new LoadSongIntoPresentationCommand($song->id()->toString()),
    );

    $dto = (new NextSlideHandler($sessionRepository))(new NextSlideCommand());

    expect($dto->currentSlideIndex)->toBe(1);
});

it('throws when jumping out of range', function (): void {
    $sessionRepository = new InMemoryPresentationSessionRepository();

    expect(fn () => (new JumpToSlideHandler($sessionRepository))(new JumpToSlideCommand(5)))
        ->toThrow(InvalidSlideIndexException::class);
});

it('sets and clears the emergency message independently of blanking', function (): void {
    $sessionRepository = new InMemoryPresentationSessionRepository();

    (new SetBlankedHandler($sessionRepository))(new SetBlankedCommand(true));
    $dto = (new SetEmergencyMessageHandler($sessionRepository))(new SetEmergencyMessageCommand('Fire drill'));

    expect($dto->isBlanked)->toBeTrue();
    expect($dto->emergencyMessage)->toBe('Fire drill');
});
