<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Command;

use DateTimeImmutable;
use Phpresent\Theme\Application\DTO\ThemeDto;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Phpresent\Theme\Domain\ValueObject\TextAlign;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;
use Ramsey\Uuid\Uuid;

/**
 * @throws \ValueError if `scope` or `textAlign` is not a valid enum value
 * @throws \Phpresent\Theme\Domain\Exception\InvalidThemeScopeException
 */
final readonly class UpdateThemeHandler
{
    public function __construct(private ThemeRepositoryInterface $themeRepository)
    {
    }

    public function __invoke(UpdateThemeCommand $command): ?ThemeDto
    {
        if (!Uuid::isValid($command->id)) {
            return null;
        }

        $theme = $this->themeRepository->get(Uuid::fromString($command->id));

        if ($theme === null) {
            return null;
        }

        $theme->update(
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
            now: new DateTimeImmutable(),
        );
        $this->themeRepository->save($theme);

        return ThemeDto::fromEntity($theme);
    }
}
