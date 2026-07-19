<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Security;

/**
 * Cross-module RBAC gate, consulted by Application handlers (not
 * controllers/middleware) before any permission-sensitive action. Takes a
 * plain actor-id string rather than an Identity `User` entity so modules
 * outside Identity can depend on this port without depending on Identity's
 * Domain layer.
 */
interface PermissionInterface
{
    /**
     * @param string|null $actorUserId Null (anonymous/unauthenticated) always yields false.
     */
    public function can(?string $actorUserId, string $permission): bool;
}
