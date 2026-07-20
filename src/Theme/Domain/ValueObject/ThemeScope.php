<?php

declare(strict_types=1);

namespace Phpresent\Theme\Domain\ValueObject;

enum ThemeScope: string
{
    case Global = 'global';
    case Song = 'song';
    case Section = 'section';
}
