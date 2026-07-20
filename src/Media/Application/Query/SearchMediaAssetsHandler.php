<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Query;

use Phpresent\Media\Application\DTO\MediaAssetDto;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;

final readonly class SearchMediaAssetsHandler
{
    public function __construct(private MediaAssetRepositoryInterface $mediaAssetRepository)
    {
    }

    /**
     * @return list<MediaAssetDto>
     */
    public function __invoke(SearchMediaAssetsQuery $query): array
    {
        $assets = trim($query->query) === ''
            ? $this->mediaAssetRepository->all($query->limit, $query->offset)
            : $this->mediaAssetRepository->search($query->query, $query->limit, $query->offset);

        return array_map(static fn ($asset) => MediaAssetDto::fromEntity($asset), $assets);
    }
}
