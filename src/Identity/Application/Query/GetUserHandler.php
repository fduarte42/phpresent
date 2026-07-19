<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Query;

use Phpresent\Identity\Application\DTO\UserDto;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Ramsey\Uuid\Uuid;

final readonly class GetUserHandler
{
    private const string PERMISSION = 'users.view';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PermissionInterface $permission,
    ) {
    }

    public function __invoke(GetUserQuery $query): ?UserDto
    {
        if (!$this->permission->can($query->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $user = $this->userRepository->get(Uuid::fromString($query->id));

        return $user === null ? null : UserDto::fromEntity($user);
    }
}
