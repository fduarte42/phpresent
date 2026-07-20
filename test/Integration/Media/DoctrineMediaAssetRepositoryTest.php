<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Media\Infrastructure\Persistence\DoctrineMediaAssetRepository;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;

function makeMediaInMemoryEntityManager(): EntityManager
{
    EntityManagerFactory::registerCustomTypes();

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [__DIR__ . '/../../../src/Media/Domain/Entity'],
        isDevMode: true,
    );

    $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
    $entityManager = new EntityManager(
        DriverManager::getConnection($dsnParser->parse('sqlite:///:memory:'), $config),
        $config,
    );

    $schemaTool = new SchemaTool($entityManager);
    $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

    return $entityManager;
}

it('persists a media asset and reloads it by id', function (): void {
    $entityManager = makeMediaInMemoryEntityManager();
    $repository = new DoctrineMediaAssetRepository($entityManager);

    $asset = new MediaAsset('slide.jpg', 'abc-slide.jpg', 'image/jpeg', 2048, 1920, 1080);
    $repository->save($asset);
    $entityManager->clear();

    $reloaded = $repository->get($asset->id());

    expect($reloaded)->not->toBeNull();
    expect($reloaded->filename())->toBe('slide.jpg');
    expect($reloaded->width())->toBe(1920);
    expect($reloaded->height())->toBe(1080);
});

it('removes a media asset', function (): void {
    $entityManager = makeMediaInMemoryEntityManager();
    $repository = new DoctrineMediaAssetRepository($entityManager);

    $asset = new MediaAsset('a.txt', 'key-a', 'text/plain', 1);
    $repository->save($asset);
    $repository->remove($asset);

    expect($repository->get($asset->id()))->toBeNull();
});

it('searches media assets case-insensitively by filename', function (): void {
    $entityManager = makeMediaInMemoryEntityManager();
    $repository = new DoctrineMediaAssetRepository($entityManager);

    $repository->save(new MediaAsset('Sunday-Slide.jpg', 'k1', 'image/jpeg', 1));
    $repository->save(new MediaAsset('sermon-notes.pdf', 'k2', 'application/pdf', 1));

    $results = $repository->search('sunday');

    expect($results)->toHaveCount(1);
    expect($results[0]->filename())->toBe('Sunday-Slide.jpg');
});
