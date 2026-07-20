<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Query;

use Phpresent\Theme\Application\DTO\ThemeDto;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;

final readonly class ListThemesHandler
{
    public function __construct(private ThemeRepositoryInterface $themeRepository)
    {
    }

    /**
     * @return list<ThemeDto>
     */
    public function __invoke(ListThemesQuery $query): array
    {
        return array_map(
            ThemeDto::fromEntity(...),
            $this->themeRepository->all($query->limit, $query->offset),
        );
    }
}
