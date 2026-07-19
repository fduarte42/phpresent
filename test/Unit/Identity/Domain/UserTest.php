<?php

declare(strict_types=1);

use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\ValueObject\Email;

function makeUser(): User
{
    return new User(
        email: new Email('operator@example.com'),
        passwordHash: 'hashed:secret',
        displayName: 'Operator',
    );
}

it('starts active with no roles', function (): void {
    $user = makeUser();

    expect($user->isActive())->toBeTrue();
    expect($user->roleIds())->toBe([]);
});

it('assigns a role, bumping updatedAt', function (): void {
    $user = makeUser();
    $roleId = Ramsey\Uuid\Uuid::uuid4()->toString();
    $now = $user->updatedAt()->modify('+1 hour');

    $user->assignRole($roleId, $now);

    expect($user->roleIds())->toBe([$roleId]);
    expect($user->updatedAt())->toBe($now);
});

it('does not duplicate a role already assigned', function (): void {
    $user = makeUser();
    $roleId = Ramsey\Uuid\Uuid::uuid4()->toString();
    $now = new DateTimeImmutable();

    $user->assignRole($roleId, $now);
    $user->assignRole($roleId, $now->modify('+1 hour'));

    expect($user->roleIds())->toBe([$roleId]);
});

it('deactivates without deleting the record', function (): void {
    $user = makeUser();

    $user->deactivate(new DateTimeImmutable());

    expect($user->isActive())->toBeFalse();
});
