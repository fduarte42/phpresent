<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\ValueObject;

use Phpresent\Identity\Domain\Exception\InvalidEmailException;

final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = mb_strtolower(trim($value));
        if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailException::forValue($value);
        }

        $this->value = $trimmed;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
