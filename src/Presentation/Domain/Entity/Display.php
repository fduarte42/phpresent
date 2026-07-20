<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phpresent\Presentation\Domain\ValueObject\DisplayRole;
use Phpresent\Presentation\Domain\ValueObject\DisplaySettings;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'displays')]
class Display
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 191)]
    private string $name;

    #[ORM\Column(type: 'string', length: 24, enumType: DisplayRole::class)]
    private DisplayRole $role;

    /** @var array<string, scalar|null> */
    #[ORM\Column(type: 'json')]
    private array $settings;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        DisplayRole $role,
        DisplaySettings $settings = new DisplaySettings(),
        ?DateTimeImmutable $now = null,
    ) {
        $now ??= new DateTimeImmutable();

        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->role = $role;
        $this->settings = $settings->toArray();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function role(): DisplayRole
    {
        return $this->role;
    }

    public function settings(): DisplaySettings
    {
        return DisplaySettings::fromArray($this->settings);
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $name, DisplayRole $role, DisplaySettings $settings, DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->role = $role;
        $this->settings = $settings->toArray();
        $this->updatedAt = $now;
    }
}
