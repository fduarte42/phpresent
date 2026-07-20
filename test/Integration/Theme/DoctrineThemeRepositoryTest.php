<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;
use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;
use Phpresent\Theme\Infrastructure\Persistence\DoctrineThemeRepository;

function makeThemeInMemoryEntityManager(): EntityManager
{
    EntityManagerFactory::registerCustomTypes();

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [__DIR__ . '/../../../src/Theme/Domain/Entity'],
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

it('persists a theme and reloads it by id', function (): void {
    $entityManager = makeThemeInMemoryEntityManager();
    $repository = new DoctrineThemeRepository($entityManager);

    $theme = new Theme(
        name: 'Sunday Morning',
        scope: ThemeScope::Section,
        sectionType: 'chorus',
        backgroundColor: '#101020',
        fontSizeScale: 1.25,
    );
    $repository->save($theme);
    $entityManager->clear();

    $reloaded = $repository->get($theme->id());

    expect($reloaded)->not->toBeNull();
    expect($reloaded->name())->toBe('Sunday Morning');
    expect($reloaded->scope())->toBe(ThemeScope::Section);
    expect($reloaded->sectionType())->toBe('chorus');
    expect($reloaded->backgroundColor())->toBe('#101020');
    expect($reloaded->fontSizeScale())->toBe(1.25);
});

it('removes a theme', function (): void {
    $entityManager = makeThemeInMemoryEntityManager();
    $repository = new DoctrineThemeRepository($entityManager);

    $theme = new Theme('Default', ThemeScope::Global);
    $repository->save($theme);
    $repository->remove($theme);

    expect($repository->get($theme->id()))->toBeNull();
});

it('lists all themes ordered by name', function (): void {
    $entityManager = makeThemeInMemoryEntityManager();
    $repository = new DoctrineThemeRepository($entityManager);

    $repository->save(new Theme('Zebra', ThemeScope::Global));
    $repository->save(new Theme('Alpha', ThemeScope::Global));

    $names = array_map(static fn (Theme $theme): string => $theme->name(), $repository->all());

    expect($names)->toBe(['Alpha', 'Zebra']);
});
