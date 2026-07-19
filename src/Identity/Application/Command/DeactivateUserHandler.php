<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

use DateTimeImmutable;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Ramsey\Uuid\Uuid;

final readonly class DeactivateUserHandler
{
    private const string PERMISSION = 'users.manage';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PermissionInterface $permission,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    public function __invoke(DeactivateUserCommand $command): ?User
    {
        if (!$this->permission->can($command->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        if (!Uuid::isValid($command->userId)) {
            return null;
        }

        $user = $this->userRepository->get(Uuid::fromString($command->userId));

        if ($user === null) {
            return null;
        }

        $user->deactivate(new DateTimeImmutable());
        $this->userRepository->save($user);

        /** @var string $actorUserId Permission check above already required a non-null actor. */
        $actorUserId = $command->actorUserId;
        $this->auditLogger->record($actorUserId, 'user.deactivated', ['userId' => $user->id()->toString()]);

        return $user;
    }
}
