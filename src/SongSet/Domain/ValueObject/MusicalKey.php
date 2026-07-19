<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Domain\ValueObject;

use Phpresent\SongSet\Domain\Exception\InvalidMusicalKeyException;

final readonly class MusicalKey
{
    private const string PATTERN = '/^[A-G](#|b)?m?$/';

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match(self::PATTERN, $trimmed) !== 1) {
            throw InvalidMusicalKeyException::forValue($value);
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
