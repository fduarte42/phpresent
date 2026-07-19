<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phpresent\Song\Domain\Entity\Song;
use Phpresent\Song\Domain\Entity\SongSection;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;
use Phpresent\Song\Infrastructure\Persistence\DoctrineSongRepository;

function makeInMemoryEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [
            __DIR__ . '/../../../src/Song/Domain/Entity',
            __DIR__ . '/../../../src/Song/Infrastructure/Persistence',
        ],
        isDevMode: true,
    );

    $entityManager = new EntityManager(
        \Doctrine\DBAL\DriverManager::getConnection(['url' => 'sqlite:///:memory:'], $config),
        $config,
    );

    $schemaTool = new SchemaTool($entityManager);
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
    $schemaTool->createSchema($metadata);

    return $entityManager;
}

it('persists a song with its sections in order and reloads it by external id', function (): void {
    $entityManager = makeInMemoryEntityManager();
    $repository = new DoctrineSongRepository($entityManager);

    $song = new Song(
        externalId: 'sbp-99',
        title: 'How Great Is Our God',
        authors: ['Chris Tomlin'],
        format: LyricFormat::ChordPro,
        sourceRevision: 'rev-1',
        sourceChecksum: 'checksum-1',
    );
    $song->replaceSections(
        new SongSection($song, position: 0, type: SectionType::Verse, content: 'The splendor of a King'),
        new SongSection($song, position: 1, type: SectionType::Chorus, content: 'How great is our God'),
    );

    $repository->save($song);
    $entityManager->clear();

    $reloaded = $repository->findByExternalId('sbp-99');

    expect($reloaded)->not->toBeNull();
    expect($reloaded->title())->toBe('How Great Is Our God');
    expect($reloaded->sections())->toHaveCount(2);
    expect($reloaded->sections()[0]->type())->toBe(SectionType::Verse);
    expect($reloaded->sections()[1]->type())->toBe(SectionType::Chorus);
});

it('searches songs case-insensitively by title', function (): void {
    $entityManager = makeInMemoryEntityManager();
    $repository = new DoctrineSongRepository($entityManager);

    $repository->save(new Song(
        externalId: 'sbp-1',
        title: 'Amazing Grace',
        authors: [],
        format: LyricFormat::PlainText,
        sourceRevision: 'r1',
        sourceChecksum: 'c1',
    ));
    $repository->save(new Song(
        externalId: 'sbp-2',
        title: 'Great Is Thy Faithfulness',
        authors: [],
        format: LyricFormat::PlainText,
        sourceRevision: 'r1',
        sourceChecksum: 'c1',
    ));

    $results = $repository->search('great');

    expect($results)->toHaveCount(1);
    expect($results[0]->title())->toBe('Great Is Thy Faithfulness');
});
