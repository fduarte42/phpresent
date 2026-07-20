<?php

declare(strict_types=1);

namespace Phpresent\Media\Domain\Repository;

use Phpresent\Media\Domain\Entity\MediaAsset;
use Ramsey\Uuid\UuidInterface;

interface MediaAssetRepositoryInterface
{
    public function get(UuidInterface $id): ?MediaAsset;

    public function save(MediaAsset $asset): void;

    public function remove(MediaAsset $asset): void;

    /**
     * @return list<MediaAsset>
     */
    public function search(string $query, int $limit = 50, int $offset = 0): array;

    /**
     * @return list<MediaAsset>
     */
    public function all(int $limit = 50, int $offset = 0): array;

    public function count(): int;
}
