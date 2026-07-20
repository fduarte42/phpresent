<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemoryThemeRepository implements ThemeRepositoryInterface
{
    /** @var array<string, Theme> */
    private array $themes = [];

    public function get(UuidInterface $id): ?Theme
    {
        return $this->themes[$id->toString()] ?? null;
    }

    public function save(Theme $theme): void
    {
        $this->themes[$theme->id()->toString()] = $theme;
    }

    public function remove(Theme $theme): void
    {
        unset($this->themes[$theme->id()->toString()]);
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_values($this->themes);
    }
}
