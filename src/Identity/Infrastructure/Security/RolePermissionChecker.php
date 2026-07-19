<?php

declare(strict_types=1);

namespace Phpresent\Identity\Infrastructure\Security;

use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Ramsey\Uuid\Uuid;

final class RolePermissionChecker implements PermissionInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roleRepository,
    ) {
    }

    public function can(?string $actorUserId, string $permission): bool
    {
        if ($actorUserId === null || !Uuid::isValid($actorUserId)) {
            return false;
        }

        $user = $this->userRepository->get(Uuid::fromString($actorUserId));

        if ($user === null || !$user->isActive()) {
            return false;
        }

        foreach ($user->roleIds() as $roleId) {
            if (!Uuid::isValid($roleId)) {
                continue;
            }

            $role = $this->roleRepository->get(Uuid::fromString($roleId));

            if ($role !== null && $role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
