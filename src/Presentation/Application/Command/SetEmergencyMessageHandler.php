<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

use DateTimeImmutable;
use Phpresent\Presentation\Application\DTO\PresentationSessionDto;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;

final readonly class SetEmergencyMessageHandler
{
    public function __construct(private PresentationSessionRepositoryInterface $sessionRepository)
    {
    }

    public function __invoke(SetEmergencyMessageCommand $command): PresentationSessionDto
    {
        $session = $this->sessionRepository->current();
        $session->setEmergencyMessage($command->message, new DateTimeImmutable());
        $this->sessionRepository->save($session);

        return PresentationSessionDto::fromEntity($session);
    }
}
