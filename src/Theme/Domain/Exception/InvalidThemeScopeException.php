<?php

declare(strict_types=1);

namespace Phpresent\Theme\Domain\Exception;

use InvalidArgumentException;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;

final class InvalidThemeScopeException extends InvalidArgumentException
{
    public static function songExternalIdRequired(): self
    {
        return new self('A Song-scoped theme requires a songExternalId.');
    }

    public static function sectionTypeRequired(): self
    {
        return new self('A Section-scoped theme requires a sectionType.');
    }

    public static function targetNotAllowed(ThemeScope $scope): self
    {
        return new self(sprintf(
            'A %s-scoped theme must not set songExternalId or sectionType.',
            $scope->value,
        ));
    }
}
