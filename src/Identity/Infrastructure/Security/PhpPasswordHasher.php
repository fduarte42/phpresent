<?php

declare(strict_types=1);

namespace Phpresent\Identity\Infrastructure\Security;

use Phpresent\Identity\Domain\Service\PasswordHasherInterface;

final class PhpPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
