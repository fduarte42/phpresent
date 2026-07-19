<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Persistence;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;

final class DoctrineAuditLogger implements AuditLoggerInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function record(string $actorUserId, string $action, array $context = []): void
    {
        $this->entityManager->persist(new AuditLog($actorUserId, $action, $context, new DateTimeImmutable()));
        $this->entityManager->flush();
    }
}
