<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

use Phpresent\Presentation\Application\DTO\DisplayDto;
use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Phpresent\Presentation\Domain\ValueObject\DisplayRole;
use Phpresent\Presentation\Domain\ValueObject\DisplaySettings;

/**
 * @throws \ValueError if `role` is not a valid `DisplayRole`
 */
final readonly class CreateDisplayHandler
{
    public function __construct(private DisplayRepositoryInterface $displayRepository)
    {
    }

    public function __invoke(CreateDisplayCommand $command): DisplayDto
    {
        $display = new Display(
            name: $command->name,
            role: DisplayRole::from($command->role),
            settings: DisplaySettings::fromArray($command->settings),
        );

        $this->displayRepository->save($display);

        return DisplayDto::fromEntity($display);
    }
}
