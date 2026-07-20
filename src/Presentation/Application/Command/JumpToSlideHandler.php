<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

use DateTimeImmutable;
use Phpresent\Presentation\Application\DTO\PresentationSessionDto;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;

/**
 * @throws \Phpresent\Presentation\Domain\Exception\InvalidSlideIndexException
 */
final readonly class JumpToSlideHandler
{
    public function __construct(private PresentationSessionRepositoryInterface $sessionRepository)
    {
    }

    public function __invoke(JumpToSlideCommand $command): PresentationSessionDto
    {
        $session = $this->sessionRepository->current();
        $session->jumpToSlide($command->index, new DateTimeImmutable());
        $this->sessionRepository->save($session);

        return PresentationSessionDto::fromEntity($session);
    }
}
