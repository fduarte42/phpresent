<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Identity\Domain\Service\PasswordHasherInterface;

/**
 * Deliberately not real hashing — Application-layer tests should never pay
 * bcrypt's cost or depend on a specific algorithm.
 */
final class FakePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $password): string
    {
        return 'hashed:' . $password;
    }

    public function verify(string $password, string $hash): bool
    {
        return $hash === 'hashed:' . $password;
    }
}
