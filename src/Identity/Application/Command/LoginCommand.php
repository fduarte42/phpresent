<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Command;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
