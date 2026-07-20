<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Command;

use Phpresent\Media\Application\DTO\MediaAssetDto;
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class UploadMediaAssetHandler
{
    public function __construct(
        private MediaAssetRepositoryInterface $mediaAssetRepository,
        private MediaStorageInterface $mediaStorage,
    ) {
    }

    public function __invoke(UploadMediaAssetCommand $command): MediaAssetDto
    {
        $storageKey = Uuid::uuid4()->toString() . '-' . $this->sanitizeFilename($command->filename);
        $dimensions = $this->mediaStorage->write($storageKey, $command->mimeType, $command->contents);

        $asset = new MediaAsset(
            filename: $command->filename,
            storageKey: $storageKey,
            mimeType: $command->mimeType,
            sizeBytes: $command->sizeBytes,
            width: $dimensions['width'],
            height: $dimensions['height'],
        );
        $this->mediaAssetRepository->save($asset);

        return MediaAssetDto::fromEntity($asset);
    }

    private function sanitizeFilename(string $filename): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($filename));

        return $safe === '' || $safe === null ? 'file' : $safe;
    }
}
