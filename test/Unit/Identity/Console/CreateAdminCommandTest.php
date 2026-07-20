<?php

declare(strict_types=1);

use Phpresent\Identity\Presentation\Console\CreateAdminCommand;
use PhpresentTest\Support\FakePasswordHasher;
use PhpresentTest\Support\InMemoryRoleRepository;
use PhpresentTest\Support\InMemoryUserRepository;
use Symfony\Component\Console\Tester\CommandTester;

function makeCreateAdminTester(
    ?InMemoryUserRepository $users = null,
    ?InMemoryRoleRepository $roles = null,
): CommandTester {
    $command = new CreateAdminCommand($users ?? new InMemoryUserRepository(), $roles ?? new InMemoryRoleRepository(), new FakePasswordHasher());

    return new CommandTester($command);
}

it('creates an admin role and user on first run', function (): void {
    $users = new InMemoryUserRepository();
    $roles = new InMemoryRoleRepository();
    $tester = makeCreateAdminTester($users, $roles);

    $exitCode = $tester->execute([
        '--email' => 'admin@example.com',
        '--password' => 'changeme123',
        '--display-name' => 'Admin',
    ]);

    expect($exitCode)->toBe(0);

    $role = $roles->findByName('admin');
    expect($role)->not->toBeNull();
    expect($role->permissions())->toBe(['users.view', 'users.manage', 'roles.view', 'roles.manage']);

    $user = $users->findByEmail('admin@example.com');
    expect($user)->not->toBeNull();
    expect($user->roleIds())->toBe([$role->id()->toString()]);
});

it('reuses an existing admin role rather than creating a duplicate', function (): void {
    $users = new InMemoryUserRepository();
    $roles = new InMemoryRoleRepository();
    makeCreateAdminTester($users, $roles)->execute([
        '--email' => 'first@example.com',
        '--password' => 'changeme123',
    ]);

    makeCreateAdminTester($users, $roles)->execute([
        '--email' => 'second@example.com',
        '--password' => 'changeme123',
    ]);

    expect($roles->all())->toHaveCount(1);
    expect($users->all())->toHaveCount(2);
});

it('fails without creating anything for an invalid email', function (): void {
    $users = new InMemoryUserRepository();
    $roles = new InMemoryRoleRepository();
    $tester = makeCreateAdminTester($users, $roles);

    $exitCode = $tester->execute(['--email' => 'not-an-email', '--password' => 'changeme123']);

    expect($exitCode)->toBe(1);
    expect($users->all())->toBe([]);
});

it('fails when the email already exists', function (): void {
    $users = new InMemoryUserRepository();
    $roles = new InMemoryRoleRepository();
    makeCreateAdminTester($users, $roles)->execute(['--email' => 'admin@example.com', '--password' => 'first-pass']);

    $exitCode = makeCreateAdminTester($users, $roles)->execute([
        '--email' => 'admin@example.com',
        '--password' => 'second-pass',
    ]);

    expect($exitCode)->toBe(1);
    expect($users->all())->toHaveCount(1);
});

it('fails when no password is given', function (): void {
    $tester = makeCreateAdminTester();

    $exitCode = $tester->execute(['--email' => 'admin@example.com']);

    expect($exitCode)->toBe(1);
});
