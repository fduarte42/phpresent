<?php

declare(strict_types=1);

namespace Phpresent\Theme\Domain\Repository;

use Phpresent\Theme\Domain\Entity\Theme;
use Ramsey\Uuid\UuidInterface;

interface ThemeRepositoryInterface
{
    public function get(UuidInterface $id): ?Theme;

    public function save(Theme $theme): void;

    public function remove(Theme $theme): void;

    /**
     * @return list<Theme>
     */
    public function all(int $limit = 50, int $offset = 0): array;
}
