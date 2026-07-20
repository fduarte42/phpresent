<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

use DateTimeImmutable;
use Phpresent\Presentation\Application\DTO\DisplayDto;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Phpresent\Presentation\Domain\ValueObject\DisplayRole;
use Phpresent\Presentation\Domain\ValueObject\DisplaySettings;
use Ramsey\Uuid\Uuid;

/**
 * @throws \ValueError if `role` is not a valid `DisplayRole`
 */
final readonly class UpdateDisplayHandler
{
    public function __construct(private DisplayRepositoryInterface $displayRepository)
    {
    }

    public function __invoke(UpdateDisplayCommand $command): ?DisplayDto
    {
        if (!Uuid::isValid($command->id)) {
            return null;
        }

        $display = $this->displayRepository->get(Uuid::fromString($command->id));

        if ($display === null) {
            return null;
        }

        $display->update(
            name: $command->name,
            role: DisplayRole::from($command->role),
            settings: DisplaySettings::fromArray($command->settings),
            now: new DateTimeImmutable(),
        );
        $this->displayRepository->save($display);

        return DisplayDto::fromEntity($display);
    }
}
