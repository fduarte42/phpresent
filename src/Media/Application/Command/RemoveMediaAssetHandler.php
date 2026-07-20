<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Command;

use Phpresent\Media\Application\Service\MediaStorageInterface;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class RemoveMediaAssetHandler
{
    public function __construct(
        private MediaAssetRepositoryInterface $mediaAssetRepository,
        private MediaStorageInterface $mediaStorage,
    ) {
    }

    /**
     * @return bool true if an asset was found and removed
     */
    public function __invoke(RemoveMediaAssetCommand $command): bool
    {
        if (!Uuid::isValid($command->id)) {
            return false;
        }

        $asset = $this->mediaAssetRepository->get(Uuid::fromString($command->id));

        if ($asset === null) {
            return false;
        }

        $this->mediaStorage->delete($asset->storageKey());
        $this->mediaAssetRepository->remove($asset);

        return true;
    }
}
