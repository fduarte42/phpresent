<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;
use Phpresent\SongSet\Domain\Entity\SongSet;
use Phpresent\SongSet\Infrastructure\Persistence\DoctrineSongSetRepository;

function makeSongSetInMemoryEntityManager(): EntityManager
{
    EntityManagerFactory::registerCustomTypes();

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [
            __DIR__ . '/../../../src/SongSet/Domain/Entity',
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

it('persists a song set with its items in order and reloads it by external id', function (): void {
    $entityManager = makeSongSetInMemoryEntityManager();
    $repository = new DoctrineSongSetRepository($entityManager);

    $songSet = new SongSet(
        externalId: 'sbp-set-99',
        name: 'Evening Service',
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
    $songSet->syncItems([
        ['songExternalId' => 'sbp-1', 'sourcePosition' => 0, 'transposedKey' => null, 'notes' => null],
        ['songExternalId' => 'sbp-2', 'sourcePosition' => 1, 'transposedKey' => null, 'notes' => null],
    ]);

    $repository->save($songSet);
    $entityManager->clear();

    $reloaded = $repository->findByExternalId('sbp-set-99');

    expect($reloaded)->not->toBeNull();
    expect($reloaded->name())->toBe('Evening Service');
    expect($reloaded->items())->toHaveCount(2);
    expect($reloaded->items()[0]->songExternalId())->toBe('sbp-1');
    expect($reloaded->items()[1]->songExternalId())->toBe('sbp-2');
});

it('searches song sets case-insensitively by name', function (): void {
    $entityManager = makeSongSetInMemoryEntityManager();
    $repository = new DoctrineSongSetRepository($entityManager);

    $repository->save(new SongSet(
        externalId: 'sbp-set-1',
        name: 'Sunday Morning',
        sourceRevision: 'r1',
        sourceChecksum: 'c1',
    ));
    $repository->save(new SongSet(
        externalId: 'sbp-set-2',
        name: 'Evening Service',
        sourceRevision: 'r1',
        sourceChecksum: 'c1',
    ));

    $results = $repository->search('evening');

    expect($results)->toHaveCount(1);
    expect($results[0]->name())->toBe('Evening Service');
});
