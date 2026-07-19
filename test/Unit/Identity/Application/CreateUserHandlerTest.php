<?php

declare(strict_types=1);

use Phpresent\Identity\Application\Command\CreateUserCommand;
use Phpresent\Identity\Application\Command\CreateUserHandler;
use Phpresent\Identity\Domain\Exception\DuplicateEmailException;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use PhpresentTest\Support\FakeAuditLogger;
use PhpresentTest\Support\FakePasswordHasher;
use PhpresentTest\Support\FakePermission;
use PhpresentTest\Support\InMemoryUserRepository;

it('creates a user, hashes the password, and audit-logs the action', function (): void {
    $auditLogger = new FakeAuditLogger();
    $handler = new CreateUserHandler(
        new InMemoryUserRepository(),
        new FakePasswordHasher(),
        new FakePermission(allow: true),
        $auditLogger,
    );

    $user = $handler(new CreateUserCommand(
        actorUserId: Ramsey\Uuid\Uuid::uuid4()->toString(),
        email: 'Operator@Example.com',
        password: 'secret',
        displayName: 'Operator',
    ));

    expect($user->email())->toBe('operator@example.com');
    expect($user->passwordHash())->toBe('hashed:secret');
    expect($auditLogger->records)->toHaveCount(1);
    expect($auditLogger->records[0]['action'])->toBe('user.created');
});

it('rejects a duplicate email', function (): void {
    $userRepository = new InMemoryUserRepository();
    $handler = new CreateUserHandler(
        $userRepository,
        new FakePasswordHasher(),
        new FakePermission(allow: true),
        new FakeAuditLogger(),
    );

    $command = new CreateUserCommand(
        actorUserId: Ramsey\Uuid\Uuid::uuid4()->toString(),
        email: 'operator@example.com',
        password: 'secret',
        displayName: 'Operator',
    );
    $handler($command);
    $handler($command);
})->throws(DuplicateEmailException::class);

it('denies creation without the users.manage permission', function (): void {
    $handler = new CreateUserHandler(
        new InMemoryUserRepository(),
        new FakePasswordHasher(),
        new FakePermission(allow: false),
        new FakeAuditLogger(),
    );

    $handler(new CreateUserCommand(
        actorUserId: Ramsey\Uuid\Uuid::uuid4()->toString(),
        email: 'operator@example.com',
        password: 'secret',
        displayName: 'Operator',
    ));
})->throws(PermissionDeniedException::class);
