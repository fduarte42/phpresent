<?php

declare(strict_types=1);

use Laminas\Diactoros\Stream;
use Phpresent\Media\Application\Query\GetMediaAssetContentHandler;
use Phpresent\Media\Application\Query\GetMediaAssetContentQuery;
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Phpresent\Media\Domain\Entity\MediaAsset;
use PhpresentTest\Support\InMemoryMediaAssetRepository;
use Psr\Http\Message\StreamInterface;

it('resolves an asset and returns its stream from storage', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $asset = new MediaAsset('a.txt', 'the-storage-key', 'text/plain', 5);
    $repository->save($asset);

    $storage = new class () implements MediaStorageInterface {
        public ?string $requestedKey = null;

        public function write(string $storageKey, string $mimeType, StreamInterface $contents): array
        {
            throw new RuntimeException('not used');
        }

        public function readStream(string $storageKey): StreamInterface
        {
            $this->requestedKey = $storageKey;
            $stream = new Stream('php://temp', 'r+');
            $stream->write('hello');
            $stream->rewind();

            return $stream;
        }

        public function delete(string $storageKey): void
        {
        }
    };

    $content = (new GetMediaAssetContentHandler($repository, $storage))(
        new GetMediaAssetContentQuery($asset->id()->toString()),
    );

    expect($content)->not->toBeNull();
    expect($content->filename)->toBe('a.txt');
    expect($content->mimeType)->toBe('text/plain');
    expect($content->stream->getContents())->toBe('hello');
    expect($storage->requestedKey)->toBe('the-storage-key');
});

it('returns null for an unknown id', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $storage = new class () implements MediaStorageInterface {
        public function write(string $storageKey, string $mimeType, StreamInterface $contents): array
        {
            throw new RuntimeException('not used');
        }

        public function readStream(string $storageKey): StreamInterface
        {
            throw new RuntimeException('not used');
        }

        public function delete(string $storageKey): void
        {
        }
    };

    $content = (new GetMediaAssetContentHandler($repository, $storage))(
        new GetMediaAssetContentQuery('11111111-1111-1111-1111-111111111111'),
    );

    expect($content)->toBeNull();
});
