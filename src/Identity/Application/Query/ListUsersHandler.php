<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Query;

use Phpresent\Identity\Application\DTO\UserDto;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;

final readonly class ListUsersHandler
{
    private const string PERMISSION = 'users.view';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PermissionInterface $permission,
    ) {
    }

    /**
     * @return list<UserDto>
     */
    public function __invoke(ListUsersQuery $query): array
    {
        if (!$this->permission->can($query->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        return array_map(
            static fn ($user) => UserDto::fromEntity($user),
            $this->userRepository->all($query->limit, $query->offset),
        );
    }
}
