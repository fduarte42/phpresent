<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\ValueObject\DisplayRole;
use Phpresent\Presentation\Domain\ValueObject\DisplaySettings;
use Phpresent\Presentation\Domain\ValueObject\Slide;
use Phpresent\Presentation\Domain\ValueObject\SlideDeck;
use Phpresent\Presentation\Domain\ValueObject\SlideSourceType;
use Phpresent\Presentation\Infrastructure\Persistence\DoctrineDisplayRepository;
use Phpresent\Presentation\Infrastructure\Persistence\DoctrinePresentationSessionRepository;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;

function makePresentationInMemoryEntityManager(): EntityManager
{
    EntityManagerFactory::registerCustomTypes();

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [__DIR__ . '/../../../src/Presentation/Domain/Entity'],
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

it('persists a display and reloads its settings', function (): void {
    $entityManager = makePresentationInMemoryEntityManager();
    $repository = new DoctrineDisplayRepository($entityManager);

    $display = new Display('Main Screen', DisplayRole::Main, new DisplaySettings(theme: 'dark', fontScale: 1.2));
    $repository->save($display);
    $entityManager->clear();

    $reloaded = $repository->get($display->id());

    expect($reloaded)->not->toBeNull();
    expect($reloaded->name())->toBe('Main Screen');
    expect($reloaded->settings()->theme)->toBe('dark');
    expect($reloaded->settings()->fontScale)->toBe(1.2);
});

it('removes a display', function (): void {
    $entityManager = makePresentationInMemoryEntityManager();
    $repository = new DoctrineDisplayRepository($entityManager);

    $display = new Display('Main Screen', DisplayRole::Main);
    $repository->save($display);
    $repository->remove($display);

    expect($repository->get($display->id()))->toBeNull();
});

it('creates the session on first access and reuses the same row afterwards', function (): void {
    $entityManager = makePresentationInMemoryEntityManager();
    $repository = new DoctrinePresentationSessionRepository($entityManager);

    $first = $repository->current();
    $entityManager->clear();
    $second = $repository->current();

    expect($second->id()->toString())->toBe($first->id()->toString());
});

it('persists a loaded deck and control flags across a reload', function (): void {
    $entityManager = makePresentationInMemoryEntityManager();
    $repository = new DoctrinePresentationSessionRepository($entityManager);

    $session = $repository->current();
    $deck = new SlideDeck(SlideSourceType::Song, 'sbp-1', [new Slide(['Line 1']), new Slide(['Line 2'])]);
    $now = new DateTimeImmutable();
    $session->loadDeck($deck, $now);
    $session->next($now);
    $session->setBlanked(true, $now);
    $repository->save($session);
    $entityManager->clear();

    $reloaded = $repository->current();

    expect($reloaded->currentDeck())->not->toBeNull();
    expect($reloaded->currentDeck()->slides)->toHaveCount(2);
    expect($reloaded->currentSlideIndex())->toBe(1);
    expect($reloaded->isBlanked())->toBeTrue();
});
