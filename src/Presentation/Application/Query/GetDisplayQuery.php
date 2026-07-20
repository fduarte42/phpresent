<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\Query;

final readonly class GetDisplayQuery
{
    public function __construct(public string $id)
    {
    }
}
