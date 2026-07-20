<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\DTO;

use Psr\Http\Message\StreamInterface;

/**
 * Streamed bytes for one asset, for `GetMediaAssetContentHandler` to hand
 * to a REST handler building a binary response — separate from
 * `MediaAssetDto` (which never carries the bytes themselves).
 */
final readonly class MediaAssetContent
{
    public function __construct(
        public string $filename,
        public string $mimeType,
        public StreamInterface $stream,
    ) {
    }
}
