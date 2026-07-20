<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\DTO;

use Phpresent\Theme\Domain\Entity\Theme;

final readonly class ThemeDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $scope,
        public ?string $songExternalId,
        public ?string $sectionType,
        public ?string $backgroundColor,
        public ?string $backgroundMediaAssetId,
        public ?string $fontFamily,
        public ?string $fontColor,
        public float $fontSizeScale,
        public string $textAlign,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Theme $theme): self
    {
        return new self(
            id: $theme->id()->toString(),
            name: $theme->name(),
            scope: $theme->scope()->value,
            songExternalId: $theme->songExternalId(),
            sectionType: $theme->sectionType(),
            backgroundColor: $theme->backgroundColor(),
            backgroundMediaAssetId: $theme->backgroundMediaAssetId(),
            fontFamily: $theme->fontFamily(),
            fontColor: $theme->fontColor(),
            fontSizeScale: $theme->fontSizeScale(),
            textAlign: $theme->textAlign()->value,
            createdAt: $theme->createdAt()->format(DATE_ATOM),
            updatedAt: $theme->updatedAt()->format(DATE_ATOM),
        );
    }
}
