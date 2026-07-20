<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemoryMediaAssetRepository implements MediaAssetRepositoryInterface
{
    /** @var array<string, MediaAsset> */
    private array $assets = [];

    public function get(UuidInterface $id): ?MediaAsset
    {
        return $this->assets[$id->toString()] ?? null;
    }

    public function save(MediaAsset $asset): void
    {
        $this->assets[$asset->id()->toString()] = $asset;
    }

    public function remove(MediaAsset $asset): void
    {
        unset($this->assets[$asset->id()->toString()]);
    }

    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        return array_values(array_filter(
            $this->assets,
            static fn (MediaAsset $asset): bool => str_contains(mb_strtolower($asset->filename()), mb_strtolower($query)),
        ));
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_values($this->assets);
    }

    public function count(): int
    {
        return count($this->assets);
    }
}
