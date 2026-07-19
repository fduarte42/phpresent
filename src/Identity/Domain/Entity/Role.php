<?php

declare(strict_types=1);

namespace Phpresent\Identity\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'roles')]
class Role
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $name;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $permissions;

    /**
     * @param string[] $permissions
     */
    public function __construct(string $name, array $permissions = [])
    {
        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->permissions = $permissions;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return string[] */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
