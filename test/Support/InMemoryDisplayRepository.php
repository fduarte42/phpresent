<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemoryDisplayRepository implements DisplayRepositoryInterface
{
    /** @var array<string, Display> */
    private array $displays = [];

    public function get(UuidInterface $id): ?Display
    {
        return $this->displays[$id->toString()] ?? null;
    }

    public function save(Display $display): void
    {
        $this->displays[$display->id()->toString()] = $display;
    }

    public function remove(Display $display): void
    {
        unset($this->displays[$display->id()->toString()]);
    }

    public function all(): array
    {
        return array_values($this->displays);
    }
}
