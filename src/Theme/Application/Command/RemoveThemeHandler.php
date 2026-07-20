<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Command;

use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class RemoveThemeHandler
{
    public function __construct(private ThemeRepositoryInterface $themeRepository)
    {
    }

    /**
     * @return bool true if a theme was found and removed
     */
    public function __invoke(RemoveThemeCommand $command): bool
    {
        if (!Uuid::isValid($command->id)) {
            return false;
        }

        $theme = $this->themeRepository->get(Uuid::fromString($command->id));

        if ($theme === null) {
            return false;
        }

        $this->themeRepository->remove($theme);

        return true;
    }
}
