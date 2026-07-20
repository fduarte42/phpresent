<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\Repository;

use Phpresent\Presentation\Domain\Entity\Display;
use Ramsey\Uuid\UuidInterface;

interface DisplayRepositoryInterface
{
    public function get(UuidInterface $id): ?Display;

    public function save(Display $display): void;

    public function remove(Display $display): void;

    /**
     * @return list<Display>
     */
    public function all(): array;
}
