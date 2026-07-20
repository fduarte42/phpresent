<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Query;

use Phpresent\Presentation\Application\DTO\DisplayDto;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;

final readonly class ListDisplaysHandler
{
    public function __construct(private DisplayRepositoryInterface $displayRepository)
    {
    }

    /**
     * @return list<DisplayDto>
     */
    public function __invoke(ListDisplaysQuery $query): array
    {
        return array_map(DisplayDto::fromEntity(...), $this->displayRepository->all());
    }
}
