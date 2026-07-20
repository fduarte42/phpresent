<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Command;

use Phpresent\Bible\Domain\Repository\BibleBookmarkRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class RemoveBookmarkHandler
{
    public function __construct(private BibleBookmarkRepositoryInterface $bookmarkRepository)
    {
    }

    /**
     * @return bool true if a bookmark was found and removed
     */
    public function __invoke(RemoveBookmarkCommand $command): bool
    {
        if (!Uuid::isValid($command->id)) {
            return false;
        }

        $bookmark = $this->bookmarkRepository->get(Uuid::fromString($command->id));

        if ($bookmark === null) {
            return false;
        }

        $this->bookmarkRepository->remove($bookmark);

        return true;
    }
}
