<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Command;

final readonly class CreateThemeCommand
{
    public function __construct(
        public string $name,
        public string $scope,
        public ?string $songExternalId = null,
        public ?string $sectionType = null,
        public ?string $backgroundColor = null,
        public ?string $backgroundMediaAssetId = null,
        public ?string $fontFamily = null,
        public ?string $fontColor = null,
        public float $fontSizeScale = 1.0,
        public string $textAlign = 'center',
    ) {
    }
}
