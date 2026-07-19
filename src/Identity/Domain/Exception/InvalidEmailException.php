<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Exception;

use InvalidArgumentException;

final class InvalidEmailException extends InvalidArgumentException
{
    public static function forValue(string $value): self
    {
        return new self(sprintf('"%s" is not a valid email address.', $value));
    }
}
