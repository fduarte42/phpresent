<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Query;

use Phpresent\Identity\Application\DTO\RoleDto;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;

final readonly class ListRolesHandler
{
    private const string PERMISSION = 'roles.view';

    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionInterface $permission,
    ) {
    }

    /**
     * @return list<RoleDto>
     */
    public function __invoke(ListRolesQuery $query): array
    {
        if (!$this->permission->can($query->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        return array_map(
            static fn ($role) => RoleDto::fromEntity($role),
            $this->roleRepository->all($query->limit, $query->offset),
        );
    }
}
