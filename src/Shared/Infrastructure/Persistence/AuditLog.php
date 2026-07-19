<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Persistence;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['actor_user_id'], name: 'idx_audit_log_actor_user_id')]
#[ORM\Index(columns: ['recorded_at'], name: 'idx_audit_log_recorded_at')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'actor_user_id', type: 'string', length: 191)]
    private string $actorUserId;

    #[ORM\Column(type: 'string', length: 191)]
    private string $action;

    /** @var array<string, scalar|array<mixed>|null> */
    #[ORM\Column(type: 'json')]
    private array $context;

    #[ORM\Column(name: 'recorded_at', type: 'datetime_immutable')]
    private DateTimeImmutable $recordedAt;

    /**
     * @param array<string, scalar|array<mixed>|null> $context
     */
    public function __construct(string $actorUserId, string $action, array $context, DateTimeImmutable $recordedAt)
    {
        $this->id = Uuid::uuid4();
        $this->actorUserId = $actorUserId;
        $this->action = $action;
        $this->context = $context;
        $this->recordedAt = $recordedAt;
    }
}
