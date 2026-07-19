<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Security;

use RuntimeException;

final class PermissionDeniedException extends RuntimeException
{
    public static function forPermission(string $permission): self
    {
        return new self(sprintf('Missing required permission "%s".', $permission));
    }
}
