<?php

declare(strict_types=1);

use Phpresent\Song\Domain\Exception\InvalidCcliNumberException;
use Phpresent\Song\Domain\ValueObject\CcliNumber;

it('accepts a numeric CCLI number', function (): void {
    $ccli = new CcliNumber('7036857');

    expect($ccli->toString())->toBe('7036857');
});

it('trims surrounding whitespace', function (): void {
    $ccli = new CcliNumber('  7036857  ');

    expect($ccli->toString())->toBe('7036857');
});

it('rejects non-numeric values', function (): void {
    new CcliNumber('not-a-number');
})->throws(InvalidCcliNumberException::class);

it('rejects an empty value', function (): void {
    new CcliNumber('   ');
})->throws(InvalidCcliNumberException::class);

it('considers two CCLI numbers with the same value equal', function (): void {
    expect((new CcliNumber('123'))->equals(new CcliNumber('123')))->toBeTrue();
    expect((new CcliNumber('123'))->equals(new CcliNumber('456')))->toBeFalse();
});
