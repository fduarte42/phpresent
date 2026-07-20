<?php

declare(strict_types=1);

namespace Phpresent\Media\Infrastructure\Flysystem;

use Laminas\Diactoros\Stream;
use League\Flysystem\FilesystemOperator;
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final readonly class FlysystemMediaStorage implements MediaStorageInterface
{
    public function __construct(private FilesystemOperator $filesystem)
    {
    }

    public function write(string $storageKey, string $mimeType, StreamInterface $contents): array
    {
        if (!str_starts_with($mimeType, 'image/')) {
            $this->filesystem->writeStream($storageKey, $this->toResource($contents));

            return ['width' => null, 'height' => null];
        }

        $bytes = $contents->getContents();
        $this->filesystem->write($storageKey, $bytes);

        $info = @getimagesizefromstring($bytes);

        return [
            'width' => $info !== false ? $info[0] : null,
            'height' => $info !== false ? $info[1] : null,
        ];
    }

    public function readStream(string $storageKey): StreamInterface
    {
        return new Stream($this->filesystem->readStream($storageKey));
    }

    public function delete(string $storageKey): void
    {
        $this->filesystem->delete($storageKey);
    }

    /**
     * @return resource
     */
    private function toResource(StreamInterface $stream)
    {
        $resource = $stream->detach();

        if (is_resource($resource)) {
            return $resource;
        }

        $resource = fopen('php://temp', 'r+');

        if ($resource === false) {
            throw new RuntimeException('Unable to allocate a temporary stream for upload.');
        }

        while (!$stream->eof()) {
            fwrite($resource, $stream->read(1_048_576));
        }
        rewind($resource);

        return $resource;
    }
}
