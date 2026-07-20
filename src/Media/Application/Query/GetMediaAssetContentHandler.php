<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Query;

use Phpresent\Media\Application\DTO\MediaAssetContent;
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class GetMediaAssetContentHandler
{
    public function __construct(
        private MediaAssetRepositoryInterface $mediaAssetRepository,
        private MediaStorageInterface $mediaStorage,
    ) {
    }

    public function __invoke(GetMediaAssetContentQuery $query): ?MediaAssetContent
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $asset = $this->mediaAssetRepository->get(Uuid::fromString($query->id));

        if ($asset === null) {
            return null;
        }

        return new MediaAssetContent(
            filename: $asset->filename(),
            mimeType: $asset->mimeType(),
            stream: $this->mediaStorage->readStream($asset->storageKey()),
        );
    }
}
