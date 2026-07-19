<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\ValueObject;

use Phpresent\Song\Domain\Exception\InvalidCcliNumberException;

final readonly class CcliNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/^\d{1,10}$/', $trimmed) !== 1) {
            throw InvalidCcliNumberException::forValue($value);
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
