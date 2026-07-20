<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Query;

final readonly class GetMediaAssetContentQuery
{
    public function __construct(public string $id)
    {
    }
}
