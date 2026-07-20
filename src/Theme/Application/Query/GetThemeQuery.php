<?php

declare(strict_types=1);

namespace Phpresent\Theme\Application\Query;

final readonly class GetThemeQuery
{
    public function __construct(public string $id)
    {
    }
}
