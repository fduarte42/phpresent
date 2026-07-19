<?php

declare(strict_types=1);

use Phpresent\SongSet\Domain\Exception\InvalidMusicalKeyException;
use Phpresent\SongSet\Domain\ValueObject\MusicalKey;

it('accepts natural, sharp, flat and minor keys', function (string $value): void {
    expect((new MusicalKey($value))->toString())->toBe($value);
})->with(['C', 'F#', 'Bb', 'Am', 'G#m']);

it('rejects an invalid key', function (): void {
    new MusicalKey('H');
})->throws(InvalidMusicalKeyException::class);

it('rejects an empty key', function (): void {
    new MusicalKey('');
})->throws(InvalidMusicalKeyException::class);
