<?php

declare(strict_types=1);

use Phpresent\Identity\Application\Command\AssignRoleCommand;
use Phpresent\Identity\Application\Command\AssignRoleHandler;
use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\ValueObject\Email;
use PhpresentTest\Support\FakeAuditLogger;
use PhpresentTest\Support\FakePermission;
use PhpresentTest\Support\InMemoryRoleRepository;
use PhpresentTest\Support\InMemoryUserRepository;

it('assigns a role to a user and audit-logs it', function (): void {
    $userRepository = new InMemoryUserRepository();
    $roleRepository = new InMemoryRoleRepository();
    $user = new User(new Email('operator@example.com'), 'hash', 'Operator');
    $role = new Role('operator', ['songs.sync']);
    $userRepository->save($user);
    $roleRepository->save($role);

    $auditLogger = new FakeAuditLogger();
    $handler = new AssignRoleHandler($userRepository, $roleRepository, new FakePermission(true), $auditLogger);

    $updated = $handler(new AssignRoleCommand(
        actorUserId: Ramsey\Uuid\Uuid::uuid4()->toString(),
        userId: $user->id()->toString(),
        roleId: $role->id()->toString(),
    ));

    expect($updated)->not->toBeNull();
    expect($updated->roleIds())->toBe([$role->id()->toString()]);
    expect($auditLogger->records[0]['action'])->toBe('user.role_assigned');
});

it('returns null for an unknown user', function (): void {
    $roleRepository = new InMemoryRoleRepository();
    $role = new Role('operator');
    $roleRepository->save($role);
    $handler = new AssignRoleHandler(new InMemoryUserRepository(), $roleRepository, new FakePermission(true), new FakeAuditLogger());

    $result = $handler(new AssignRoleCommand(
        actorUserId: Ramsey\Uuid\Uuid::uuid4()->toString(),
        userId: Ramsey\Uuid\Uuid::uuid4()->toString(),
        roleId: $role->id()->toString(),
    ));

    expect($result)->toBeNull();
});
