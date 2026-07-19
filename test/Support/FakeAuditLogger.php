<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;

final class FakeAuditLogger implements AuditLoggerInterface
{
    /** @var list<array{actorUserId: string, action: string, context: array<string, mixed>}> */
    public array $records = [];

    public function record(string $actorUserId, string $action, array $context = []): void
    {
        $this->records[] = ['actorUserId' => $actorUserId, 'action' => $action, 'context' => $context];
    }
}
