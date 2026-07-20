<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Query;

use Phpresent\Media\Application\DTO\MediaAssetDto;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class GetMediaAssetHandler
{
    public function __construct(private MediaAssetRepositoryInterface $mediaAssetRepository)
    {
    }

    public function __invoke(GetMediaAssetQuery $query): ?MediaAssetDto
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $asset = $this->mediaAssetRepository->get(Uuid::fromString($query->id));

        return $asset === null ? null : MediaAssetDto::fromEntity($asset);
    }
}
