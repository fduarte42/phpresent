<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Query;

use Phpresent\Theme\Application\DTO\ThemeDto;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class GetThemeHandler
{
    public function __construct(private ThemeRepositoryInterface $themeRepository)
    {
    }

    public function __invoke(GetThemeQuery $query): ?ThemeDto
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $theme = $this->themeRepository->get(Uuid::fromString($query->id));

        return $theme === null ? null : ThemeDto::fromEntity($theme);
    }
}
