<?php

declare(strict_types=1);

namespace Phpresent\Media\Application\Command;

final readonly class RemoveMediaAssetCommand
{
    public function __construct(public string $id)
    {
    }
}
