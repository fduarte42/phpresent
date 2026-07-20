<?php

declare(strict_types=1);

namespace Phpresent\Backup\Application\Command;

final readonly class ExportBackupCommand
{
    public function __construct(public ?string $actorUserId)
    {
    }
}
