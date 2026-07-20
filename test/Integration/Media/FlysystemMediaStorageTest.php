<?php

declare(strict_types=1);

use Laminas\Diactoros\Stream;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Phpresent\Media\Infrastructure\Flysystem\FlysystemMediaStorage;

/**
 * @return array{0: FlysystemMediaStorage, 1: string}
 */
function makeFlysystemMediaStorage(): array
{
    $root = sys_get_temp_dir() . '/phpresent-media-test-' . bin2hex(random_bytes(8));
    mkdir($root, 0775, true);
    $filesystem = new Filesystem(new LocalFilesystemAdapter($root));

    return [new FlysystemMediaStorage($filesystem), $root];
}

function removeDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $full = $path . '/' . $entry;
        is_dir($full) ? removeDirectory($full) : unlink($full);
    }

    rmdir($path);
}

function streamFromString(string $contents): Stream
{
    $stream = new Stream('php://temp', 'r+');
    $stream->write($contents);
    $stream->rewind();

    return $stream;
}

it('streams non-image content to storage without reporting dimensions', function (): void {
    [$storage, $root] = makeFlysystemMediaStorage();

    try {
        $dimensions = $storage->write('doc.txt', 'text/plain', streamFromString('hello world'));

        expect($dimensions)->toBe(['width' => null, 'height' => null]);
        expect($storage->readStream('doc.txt')->getContents())->toBe('hello world');
    } finally {
        removeDirectory($root);
    }
});

it('extracts real dimensions from image bytes', function (): void {
    [$storage, $root] = makeFlysystemMediaStorage();

    try {
        // A minimal valid 1x1 PNG.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );

        $dimensions = $storage->write('pixel.png', 'image/png', streamFromString($png));

        expect($dimensions)->toBe(['width' => 1, 'height' => 1]);
    } finally {
        removeDirectory($root);
    }
});

it('deletes stored content', function (): void {
    [$storage, $root] = makeFlysystemMediaStorage();

    try {
        $storage->write('to-delete.txt', 'text/plain', streamFromString('bye'));
        $storage->delete('to-delete.txt');

        expect(fn () => $storage->readStream('to-delete.txt'))->toThrow(Exception::class);
    } finally {
        removeDirectory($root);
    }
});
