<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

use DateTimeImmutable;
use Phpresent\Presentation\Application\DTO\PresentationSessionDto;
use Phpresent\Presentation\Application\Service\SlideComposer;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class LoadSongIntoPresentationHandler
{
    public function __construct(
        private SongRepositoryInterface $songRepository,
        private SlideComposer $slideComposer,
        private PresentationSessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(LoadSongIntoPresentationCommand $command): ?PresentationSessionDto
    {
        if (!Uuid::isValid($command->songId)) {
            return null;
        }

        $song = $this->songRepository->get(Uuid::fromString($command->songId));

        if ($song === null) {
            return null;
        }

        $session = $this->sessionRepository->current();
        $session->loadDeck($this->slideComposer->compose($song), new DateTimeImmutable());
        $this->sessionRepository->save($session);

        return PresentationSessionDto::fromEntity($session);
    }
}
