<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Exception;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public static function create(): self
    {
        // Deliberately generic — never reveal whether the email or the
        // password was the part that didn't match.
        return new self('Invalid email or password.');
    }
}
