<?php

declare(strict_types=1);

use Phpresent\Media\Application\Command\RemoveMediaAssetCommand;
use Phpresent\Media\Application\Command\RemoveMediaAssetHandler;
use Phpresent\Media\Domain\Entity\MediaAsset;
use PhpresentTest\Support\FakeMediaStorage;
use PhpresentTest\Support\InMemoryMediaAssetRepository;

it('removes the asset from the repository and deletes its stored bytes', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $storage = new FakeMediaStorage();
    $asset = new MediaAsset('a.txt', 'storage-key-1', 'text/plain', 1);
    $repository->save($asset);

    $removed = (new RemoveMediaAssetHandler($repository, $storage))(new RemoveMediaAssetCommand($asset->id()->toString()));

    expect($removed)->toBeTrue();
    expect($repository->count())->toBe(0);
    expect($storage->lastDeletedKey)->toBe('storage-key-1');
});

it('returns false for an unknown id without touching storage', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $storage = new FakeMediaStorage();

    $removed = (new RemoveMediaAssetHandler($repository, $storage))(
        new RemoveMediaAssetCommand('11111111-1111-1111-1111-111111111111'),
    );

    expect($removed)->toBeFalse();
    expect($storage->lastDeletedKey)->toBeNull();
});
