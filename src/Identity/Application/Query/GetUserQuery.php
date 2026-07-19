<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Query;

final readonly class GetUserQuery
{
    public function __construct(
        public ?string $actorUserId,
        public string $id,
    ) {
    }
}
