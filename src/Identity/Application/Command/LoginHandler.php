<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

use Phpresent\Identity\Application\DTO\UserDto;
use Phpresent\Identity\Domain\Exception\InvalidCredentialsException;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Identity\Domain\Service\PasswordHasherInterface;

/**
 * Deliberately not permission-gated — this command *is* the gate. Never
 * touches HTTP session state; the Presentation login handler writes the
 * returned user id into the session (docs/sdd.md §18.2).
 */
final readonly class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(LoginCommand $command): UserDto
    {
        $user = $this->userRepository->findByEmail(mb_strtolower(trim($command->email)));

        if ($user === null || !$user->isActive() || !$this->passwordHasher->verify($command->password, $user->passwordHash())) {
            throw InvalidCredentialsException::create();
        }

        return UserDto::fromEntity($user);
    }
}
