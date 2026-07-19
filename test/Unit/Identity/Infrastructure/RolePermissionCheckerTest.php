<?php

declare(strict_types=1);

use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\ValueObject\Email;
use Phpresent\Identity\Infrastructure\Security\RolePermissionChecker;
use PhpresentTest\Support\InMemoryRoleRepository;
use PhpresentTest\Support\InMemoryUserRepository;

it('grants a permission held by one of the user\'s roles', function (): void {
    $userRepository = new InMemoryUserRepository();
    $roleRepository = new InMemoryRoleRepository();
    $role = new Role('operator', ['songs.sync']);
    $roleRepository->save($role);
    $user = new User(new Email('operator@example.com'), 'hash', 'Operator', [$role->id()->toString()]);
    $userRepository->save($user);

    $checker = new RolePermissionChecker($userRepository, $roleRepository);

    expect($checker->can($user->id()->toString(), 'songs.sync'))->toBeTrue();
    expect($checker->can($user->id()->toString(), 'users.manage'))->toBeFalse();
});

it('denies a null actor', function (): void {
    $checker = new RolePermissionChecker(new InMemoryUserRepository(), new InMemoryRoleRepository());

    expect($checker->can(null, 'songs.sync'))->toBeFalse();
});

it('denies an unknown actor id', function (): void {
    $checker = new RolePermissionChecker(new InMemoryUserRepository(), new InMemoryRoleRepository());

    expect($checker->can(Ramsey\Uuid\Uuid::uuid4()->toString(), 'songs.sync'))->toBeFalse();
});

it('denies a deactivated user regardless of role', function (): void {
    $userRepository = new InMemoryUserRepository();
    $roleRepository = new InMemoryRoleRepository();
    $role = new Role('operator', ['songs.sync']);
    $roleRepository->save($role);
    $user = new User(new Email('operator@example.com'), 'hash', 'Operator', [$role->id()->toString()]);
    $user->deactivate(new DateTimeImmutable());
    $userRepository->save($user);

    $checker = new RolePermissionChecker($userRepository, $roleRepository);

    expect($checker->can($user->id()->toString(), 'songs.sync'))->toBeFalse();
});
