<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Command;

use Phpresent\Theme\Application\DTO\ThemeDto;
use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Phpresent\Theme\Domain\ValueObject\TextAlign;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;

/**
 * @throws \ValueError if `scope` or `textAlign` is not a valid enum value
 * @throws \Phpresent\Theme\Domain\Exception\InvalidThemeScopeException
 */
final readonly class CreateThemeHandler
{
    public function __construct(private ThemeRepositoryInterface $themeRepository)
    {
    }

    public function __invoke(CreateThemeCommand $command): ThemeDto
    {
        $theme = new Theme(
            name: $command->name,
            scope: ThemeScope::from($command->scope),
            songExternalId: $command->songExternalId,
            sectionType: $command->sectionType,
            backgroundColor: $command->backgroundColor,
            backgroundMediaAssetId: $command->backgroundMediaAssetId,
            fontFamily: $command->fontFamily,
            fontColor: $command->fontColor,
            fontSizeScale: $command->fontSizeScale,
            textAlign: TextAlign::from($command->textAlign),
        );
        $this->themeRepository->save($theme);

        return ThemeDto::fromEntity($theme);
    }
}
