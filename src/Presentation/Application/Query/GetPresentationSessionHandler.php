<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Query;

use Phpresent\Presentation\Application\DTO\PresentationSessionDto;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;

final readonly class GetPresentationSessionHandler
{
    public function __construct(private PresentationSessionRepositoryInterface $sessionRepository)
    {
    }

    public function __invoke(GetPresentationSessionQuery $query): PresentationSessionDto
    {
        return PresentationSessionDto::fromEntity($this->sessionRepository->current());
    }
}
