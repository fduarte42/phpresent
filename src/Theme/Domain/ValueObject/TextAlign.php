<?php

declare(strict_types=1);

namespace Phpresent\Theme\Domain\ValueObject;

enum TextAlign: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
}
