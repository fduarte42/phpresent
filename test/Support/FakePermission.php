<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Shared\Domain\Security\PermissionInterface;

final class FakePermission implements PermissionInterface
{
    public function __construct(private readonly bool $allow = true)
    {
    }

    public function can(?string $actorUserId, string $permission): bool
    {
        return $this->allow && $actorUserId !== null;
    }
}
