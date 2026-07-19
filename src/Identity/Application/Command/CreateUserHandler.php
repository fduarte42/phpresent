<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Exception\DuplicateEmailException;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Identity\Domain\Service\PasswordHasherInterface;
use Phpresent\Identity\Domain\ValueObject\Email;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;

final readonly class CreateUserHandler
{
    private const string PERMISSION = 'users.manage';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private PermissionInterface $permission,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    public function __invoke(CreateUserCommand $command): User
    {
        if (!$this->permission->can($command->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        $email = new Email($command->email);

        if ($this->userRepository->findByEmail($email->toString()) !== null) {
            throw DuplicateEmailException::forValue($email->toString());
        }

        $user = new User(
            email: $email,
            passwordHash: $this->passwordHasher->hash($command->password),
            displayName: $command->displayName,
            roleIds: $command->roleIds,
        );
        $this->userRepository->save($user);

        /** @var string $actorUserId Permission check above already required a non-null actor. */
        $actorUserId = $command->actorUserId;
        $this->auditLogger->record($actorUserId, 'user.created', [
            'userId' => $user->id()->toString(),
            'email' => $email->toString(),
        ]);

        return $user;
    }
}
