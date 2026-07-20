<?php

declare(strict_types=1);

namespace Phpresent\Backup\Application\Command;

use Psr\Http\Message\StreamInterface;

final readonly class ImportBackupCommand
{
    public function __construct(
        public ?string $actorUserId,
        public StreamInterface $archive,
    ) {
    }
}
