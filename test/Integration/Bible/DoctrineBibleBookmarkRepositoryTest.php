<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Phpresent\Bible\Infrastructure\Persistence\DoctrineBibleBookmarkRepository;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;

function makeBibleInMemoryEntityManager(): EntityManager
{
    EntityManagerFactory::registerCustomTypes();

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [__DIR__ . '/../../../src/Bible/Domain/Entity'],
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

it('persists a bookmark and reloads it by id', function (): void {
    $entityManager = makeBibleInMemoryEntityManager();
    $repository = new DoctrineBibleBookmarkRepository($entityManager);

    $bookmark = new BibleBookmark(
        translationId: 'kjv',
        book: 'Psalm',
        chapter: 23,
        startVerse: 1,
        endVerse: 6,
        label: 'Funeral service',
    );
    $repository->save($bookmark);
    $entityManager->clear();

    $reloaded = $repository->get($bookmark->id());

    expect($reloaded)->not->toBeNull();
    expect($reloaded->book())->toBe('Psalm');
    expect($reloaded->startVerse())->toBe(1);
    expect($reloaded->label())->toBe('Funeral service');
});

it('removes a bookmark', function (): void {
    $entityManager = makeBibleInMemoryEntityManager();
    $repository = new DoctrineBibleBookmarkRepository($entityManager);

    $bookmark = new BibleBookmark('kjv', 'John', 3);
    $repository->save($bookmark);
    $repository->remove($bookmark);

    expect($repository->get($bookmark->id()))->toBeNull();
});

it('lists bookmarks newest first', function (): void {
    $entityManager = makeBibleInMemoryEntityManager();
    $repository = new DoctrineBibleBookmarkRepository($entityManager);

    $first = new BibleBookmark('kjv', 'Genesis', 1, now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $second = new BibleBookmark('kjv', 'John', 3, now: new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
    $repository->save($first);
    $repository->save($second);

    $books = array_map(static fn (BibleBookmark $bookmark): string => $bookmark->book(), $repository->all());

    expect($books)->toBe(['John', 'Genesis']);
});
