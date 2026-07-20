<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

use DateTimeImmutable;
use Phpresent\Presentation\Application\DTO\PresentationSessionDto;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;

final readonly class SetBlankedHandler
{
    public function __construct(private PresentationSessionRepositoryInterface $sessionRepository)
    {
    }

    public function __invoke(SetBlankedCommand $command): PresentationSessionDto
    {
        $session = $this->sessionRepository->current();
        $session->setBlanked($command->blanked, new DateTimeImmutable());
        $this->sessionRepository->save($session);

        return PresentationSessionDto::fromEntity($session);
    }
}
