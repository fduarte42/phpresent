<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phpresent\Identity\Domain\ValueObject\Email;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_users_email')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 320, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password_hash', type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'display_name', type: 'string', length: 191)]
    private string $displayName;

    /** @var string[] */
    #[ORM\Column(name: 'role_ids', type: 'json')]
    private array $roleIds;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param string[] $roleIds
     */
    public function __construct(
        Email $email,
        string $passwordHash,
        string $displayName,
        array $roleIds = [],
        ?DateTimeImmutable $now = null,
    ) {
        $now ??= new DateTimeImmutable();

        $this->id = Uuid::uuid4();
        $this->email = $email->toString();
        $this->passwordHash = $passwordHash;
        $this->displayName = $displayName;
        $this->roleIds = $roleIds;
        $this->isActive = true;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    /** @return string[] */
    public function roleIds(): array
    {
        return $this->roleIds;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function assignRole(string $roleId, DateTimeImmutable $now): void
    {
        if (in_array($roleId, $this->roleIds, true)) {
            return;
        }

        $this->roleIds[] = $roleId;
        $this->updatedAt = $now;
    }

    public function deactivate(DateTimeImmutable $now): void
    {
        $this->isActive = false;
        $this->updatedAt = $now;
    }
}
