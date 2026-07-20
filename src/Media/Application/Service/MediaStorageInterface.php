<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Service;

use Psr\Http\Message\StreamInterface;

/**
 * Port through which the Application layer stores/retrieves asset bytes,
 * without knowing anything about Flysystem — same shape as
 * `Song\Application\Service\SongSourceInterface`. Implemented by
 * `Media\Infrastructure\Flysystem\FlysystemMediaStorage`.
 *
 * `StreamInterface` (not a raw string) is used for content so large
 * uploads (video) are never fully buffered in memory as a PHP string —
 * this is the same "PSR interfaces are the one standing Infrastructure-ish
 * exception" carve-out already established for `LoggerInterface` (SDD §2).
 */
interface MediaStorageInterface
{
    /**
     * `$mimeType` lets the implementation decide, before touching any
     * bytes, whether this is worth inspecting for dimensions — only image
     * content is ever fully buffered in memory (via `getimagesizefromstring()`,
     * which needs the whole byte string); everything else streams straight
     * through to storage without ever being fully buffered.
     *
     * @return array{width: ?int, height: ?int} dimensions when the content
     *         is a raster image `getimagesize()` can inspect; null/null
     *         otherwise (video/audio/documents, or an unrecognized image
     *         format) — never fabricated.
     */
    public function write(string $storageKey, string $mimeType, StreamInterface $contents): array;

    public function readStream(string $storageKey): StreamInterface;

    public function delete(string $storageKey): void;
}
