<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Audit;

/**
 * Append-only audit trail for admin/RBAC actions, consulted by Application
 * handlers after a successful permission-gated write (see
 * docs/sdd.md §18.2). Never used to log reads.
 */
interface AuditLoggerInterface
{
    /**
     * @param array<string, scalar|array<mixed>|null> $context
     */
    public function record(string $actorUserId, string $action, array $context = []): void;
}
