<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\DTO;

use Phpresent\Media\Domain\Entity\MediaAsset;

final readonly class MediaAssetDto
{
    public function __construct(
        public string $id,
        public string $filename,
        public string $mimeType,
        public int $sizeBytes,
        public string $kind,
        public ?int $width,
        public ?int $height,
        public string $uploadedAt,
    ) {
    }

    public static function fromEntity(MediaAsset $asset): self
    {
        return new self(
            id: $asset->id()->toString(),
            filename: $asset->filename(),
            mimeType: $asset->mimeType(),
            sizeBytes: $asset->sizeBytes(),
            kind: $asset->kind()->value,
            width: $asset->width(),
            height: $asset->height(),
            uploadedAt: $asset->uploadedAt()->format(DATE_ATOM),
        );
    }
}
