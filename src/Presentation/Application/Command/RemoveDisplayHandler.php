<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Command;

use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class RemoveDisplayHandler
{
    public function __construct(private DisplayRepositoryInterface $displayRepository)
    {
    }

    /**
     * @return bool true if a display was found and removed
     */
    public function __invoke(RemoveDisplayCommand $command): bool
    {
        if (!Uuid::isValid($command->id)) {
            return false;
        }

        $display = $this->displayRepository->get(Uuid::fromString($command->id));

        if ($display === null) {
            return false;
        }

        $this->displayRepository->remove($display);

        return true;
    }
}
