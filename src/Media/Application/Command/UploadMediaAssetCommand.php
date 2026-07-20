<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Command;

use Psr\Http\Message\StreamInterface;

final readonly class UploadMediaAssetCommand
{
    public function __construct(
        public string $filename,
        public string $mimeType,
        public int $sizeBytes,
        public StreamInterface $contents,
    ) {
    }
}
