<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Laminas\Diactoros\Stream;
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class FakeMediaStorage implements MediaStorageInterface
{
    /** @var array<string, string> */
    private array $contents = [];

    /** @var array{width: ?int, height: ?int} */
    public array $nextDimensions = ['width' => null, 'height' => null];

    public ?string $lastWrittenKey = null;
    public ?string $lastDeletedKey = null;

    public function write(string $storageKey, string $mimeType, StreamInterface $contents): array
    {
        $this->lastWrittenKey = $storageKey;
        $this->contents[$storageKey] = $contents->getContents();

        return $this->nextDimensions;
    }

    public function readStream(string $storageKey): StreamInterface
    {
        if (!isset($this->contents[$storageKey])) {
            throw new RuntimeException("No fake content written for \"{$storageKey}\".");
        }

        return new Stream('data://text/plain,' . $this->contents[$storageKey]);
    }

    public function delete(string $storageKey): void
    {
        $this->lastDeletedKey = $storageKey;
        unset($this->contents[$storageKey]);
    }
}
