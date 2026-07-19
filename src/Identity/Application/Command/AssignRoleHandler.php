<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

use DateTimeImmutable;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Ramsey\Uuid\Uuid;

final readonly class AssignRoleHandler
{
    private const string PERMISSION = 'users.manage';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleRepositoryInterface $roleRepository,
        private PermissionInterface $permission,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    /**
     * Returns null when the user or role id doesn't resolve to anything —
     * the Presentation handler maps that to a 404, same convention as
     * GetSongSetHandler (docs/sdd.md §17.2).
     */
    public function __invoke(AssignRoleCommand $command): ?User
    {
        if (!$this->permission->can($command->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        if (!Uuid::isValid($command->userId) || !Uuid::isValid($command->roleId)) {
            return null;
        }

        $user = $this->userRepository->get(Uuid::fromString($command->userId));
        $role = $this->roleRepository->get(Uuid::fromString($command->roleId));

        if ($user === null || $role === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $user->assignRole($role->id()->toString(), $now);
        $this->userRepository->save($user);

        /** @var string $actorUserId Permission check above already required a non-null actor. */
        $actorUserId = $command->actorUserId;
        $this->auditLogger->record($actorUserId, 'user.role_assigned', [
            'userId' => $user->id()->toString(),
            'roleId' => $role->id()->toString(),
        ]);

        return $user;
    }
}
