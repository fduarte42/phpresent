<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\ValueObject\Email;
use Phpresent\Identity\Infrastructure\Persistence\DoctrineRoleRepository;
use Phpresent\Identity\Infrastructure\Persistence\DoctrineUserRepository;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;

function makeIdentityInMemoryEntityManager(): EntityManager
{
    EntityManagerFactory::registerCustomTypes();

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [
            __DIR__ . '/../../../src/Identity/Domain/Entity',
        ],
        isDevMode: true,
    );

    $dsnParser = new \Doctrine\DBAL\Tools\DsnParser(['sqlite' => 'pdo_sqlite']);
    $entityManager = new EntityManager(
        \Doctrine\DBAL\DriverManager::getConnection($dsnParser->parse('sqlite:///:memory:'), $config),
        $config,
    );

    $schemaTool = new SchemaTool($entityManager);
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
    $schemaTool->createSchema($metadata);

    return $entityManager;
}

it('persists a user and reloads it by email', function (): void {
    $entityManager = makeIdentityInMemoryEntityManager();
    $repository = new DoctrineUserRepository($entityManager);

    $user = new User(new Email('operator@example.com'), 'hashed', 'Operator', ['role-1']);
    $repository->save($user);
    $entityManager->clear();

    $reloaded = $repository->findByEmail('operator@example.com');

    expect($reloaded)->not->toBeNull();
    expect($reloaded->displayName())->toBe('Operator');
    expect($reloaded->roleIds())->toBe(['role-1']);
});

it('persists a role and reloads it by name', function (): void {
    $entityManager = makeIdentityInMemoryEntityManager();
    $repository = new DoctrineRoleRepository($entityManager);

    $role = new Role('admin', ['users.manage', 'roles.manage']);
    $repository->save($role);
    $entityManager->clear();

    $reloaded = $repository->findByName('admin');

    expect($reloaded)->not->toBeNull();
    expect($reloaded->permissions())->toBe(['users.manage', 'roles.manage']);
});
