<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Service;

/**
 * Kept swappable so tests never touch a real hashing algorithm and a
 * future algorithm change is an Infrastructure-only swap (see
 * docs/sdd.md §18.1).
 */
interface PasswordHasherInterface
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;
}
