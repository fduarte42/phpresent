<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Exception;

use RuntimeException;

final class DuplicateEmailException extends RuntimeException
{
    public static function forValue(string $email): self
    {
        return new self(sprintf('A user with email "%s" already exists.', $email));
    }
}
