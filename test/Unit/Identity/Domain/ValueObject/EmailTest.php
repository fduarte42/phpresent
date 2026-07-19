<?php

declare(strict_types=1);

use Phpresent\Identity\Domain\Exception\InvalidEmailException;
use Phpresent\Identity\Domain\ValueObject\Email;

it('accepts a valid email and lowercases it', function (): void {
    expect((new Email('Person@Example.COM'))->toString())->toBe('person@example.com');
});

it('trims surrounding whitespace', function (): void {
    expect((new Email('  person@example.com  '))->toString())->toBe('person@example.com');
});

it('rejects a malformed email', function (): void {
    new Email('not-an-email');
})->throws(InvalidEmailException::class);

it('rejects an empty value', function (): void {
    new Email('');
})->throws(InvalidEmailException::class);

it('considers two emails with the same value equal', function (): void {
    expect((new Email('a@example.com'))->equals(new Email('a@example.com')))->toBeTrue();
});
