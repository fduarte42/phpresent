<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Command;

use Phpresent\Bible\Application\DTO\BibleBookmarkDto;
use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Phpresent\Bible\Domain\Repository\BibleBookmarkRepositoryInterface;

final readonly class CreateBookmarkHandler
{
    public function __construct(private BibleBookmarkRepositoryInterface $bookmarkRepository)
    {
    }

    public function __invoke(CreateBookmarkCommand $command): BibleBookmarkDto
    {
        $bookmark = new BibleBookmark(
            translationId: $command->translationId,
            book: $command->book,
            chapter: $command->chapter,
            startVerse: $command->startVerse,
            endVerse: $command->endVerse,
            label: $command->label,
        );
        $this->bookmarkRepository->save($bookmark);

        return BibleBookmarkDto::fromEntity($bookmark);
    }
}
