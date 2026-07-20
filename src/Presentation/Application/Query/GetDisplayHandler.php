<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Query;

use Phpresent\Presentation\Application\DTO\DisplayDto;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Ramsey\Uuid\Uuid;

final readonly class GetDisplayHandler
{
    public function __construct(private DisplayRepositoryInterface $displayRepository)
    {
    }

    public function __invoke(GetDisplayQuery $query): ?DisplayDto
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $display = $this->displayRepository->get(Uuid::fromString($query->id));

        return $display === null ? null : DisplayDto::fromEntity($display);
    }
}
