<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\Exception;

use InvalidArgumentException;

final class InvalidMusicalKeyException extends InvalidArgumentException
{
    public static function forValue(string $value): self
    {
        return new self(sprintf('"%s" is not a valid musical key.', $value));
    }
}
