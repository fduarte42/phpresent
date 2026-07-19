<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\Exception;

use InvalidArgumentException;

final class InvalidCcliNumberException extends InvalidArgumentException
{
    public static function forValue(string $value): self
    {
        return new self(sprintf('"%s" is not a valid CCLI song number.', $value));
    }
}
