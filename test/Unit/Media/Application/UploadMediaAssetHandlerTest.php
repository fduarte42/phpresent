<?php

declare(strict_types=1);

use Laminas\Diactoros\Stream;
use Phpresent\Media\Application\Command\UploadMediaAssetCommand;
use Phpresent\Media\Application\Command\UploadMediaAssetHandler;
use Phpresent\Media\Domain\ValueObject\MediaKind;
use PhpresentTest\Support\FakeMediaStorage;
use PhpresentTest\Support\InMemoryMediaAssetRepository;
use Ramsey\Uuid\Uuid;

function streamOf(string $contents): Stream
{
    $stream = new Stream('php://temp', 'r+');
    $stream->write($contents);
    $stream->rewind();

    return $stream;
}

it('stores the upload and persists a MediaAsset with a sanitized, unique storage key', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $storage = new FakeMediaStorage();
    $handler = new UploadMediaAssetHandler($repository, $storage);

    $dto = $handler(new UploadMediaAssetCommand(
        filename: '../weird name!.txt',
        mimeType: 'text/plain',
        sizeBytes: 5,
        contents: streamOf('hello'),
    ));

    expect($dto->filename)->toBe('../weird name!.txt');
    expect($dto->kind)->toBe(MediaKind::Document->value);
    expect($repository->count())->toBe(1);

    $saved = $repository->get(Uuid::fromString($dto->id));
    expect($saved)->not->toBeNull();
    expect($saved->storageKey())->not->toContain('..');
    expect($saved->storageKey())->not->toContain('!');
    expect($saved->storageKey())->not->toContain(' ');
    expect($storage->lastWrittenKey)->toBe($saved->storageKey());
});

it('passes through image dimensions reported by MediaStorageInterface', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $storage = new FakeMediaStorage();
    $storage->nextDimensions = ['width' => 640, 'height' => 480];
    $handler = new UploadMediaAssetHandler($repository, $storage);

    $dto = $handler(new UploadMediaAssetCommand(
        filename: 'photo.png',
        mimeType: 'image/png',
        sizeBytes: 10,
        contents: streamOf('fake-bytes'),
    ));

    expect($dto->width)->toBe(640);
    expect($dto->height)->toBe(480);
});
