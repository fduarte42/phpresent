<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Exception\DuplicateRoleNameException;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;

final readonly class CreateRoleHandler
{
    private const string PERMISSION = 'roles.manage';

    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionInterface $permission,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    public function __invoke(CreateRoleCommand $command): Role
    {
        if (!$this->permission->can($command->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        if ($this->roleRepository->findByName($command->name) !== null) {
            throw DuplicateRoleNameException::forValue($command->name);
        }

        $role = new Role($command->name, $command->permissions);
        $this->roleRepository->save($role);

        /** @var string $actorUserId Permission check above already required a non-null actor. */
        $actorUserId = $command->actorUserId;
        $this->auditLogger->record($actorUserId, 'role.created', [
            'roleId' => $role->id()->toString(),
            'name' => $role->name(),
        ]);

        return $role;
    }
}
