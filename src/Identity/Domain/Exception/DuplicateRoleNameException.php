<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Exception;

use RuntimeException;

final class DuplicateRoleNameException extends RuntimeException
{
    public static function forValue(string $name): self
    {
        return new self(sprintf('A role named "%s" already exists.', $name));
    }
}
