<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phpresent\SongSet\Domain\ValueObject\MusicalKey;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'song_set_items')]
#[ORM\UniqueConstraint(name: 'uniq_song_set_source_position', columns: ['song_set_id', 'source_position'])]
#[ORM\Index(columns: ['song_set_id'], name: 'idx_song_set_items_song_set_id')]
class SongSetItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: SongSet::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'song_set_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SongSet $songSet;

    /**
     * References a Song by its SongbookPro external id, not a Doctrine
     * relation — SongSet and Song are separate aggregates in separate
     * modules and must not share a cross-module entity association.
     */
    #[ORM\Column(name: 'song_external_id', type: 'string', length: 191)]
    private string $songExternalId;

    #[ORM\Column(name: 'source_position', type: 'integer')]
    private int $sourcePosition;

    /**
     * Local-only reorder override. Null means "follow sourcePosition".
     * Never sent back to SongbookPro.
     */
    #[ORM\Column(name: 'local_position', type: 'integer', nullable: true)]
    private ?int $localPosition;

    #[ORM\Column(name: 'transposed_key', type: 'string', length: 8, nullable: true)]
    private ?string $transposedKey;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    public function __construct(
        SongSet $songSet,
        string $songExternalId,
        int $sourcePosition,
        ?MusicalKey $transposedKey = null,
        ?string $notes = null,
        ?int $localPosition = null,
    ) {
        $this->id = Uuid::uuid4();
        $this->songSet = $songSet;
        $this->songExternalId = $songExternalId;
        $this->sourcePosition = $sourcePosition;
        $this->transposedKey = $transposedKey?->toString();
        $this->notes = $notes;
        $this->localPosition = $localPosition;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function songExternalId(): string
    {
        return $this->songExternalId;
    }

    public function sourcePosition(): int
    {
        return $this->sourcePosition;
    }

    public function localPosition(): ?int
    {
        return $this->localPosition;
    }

    public function effectivePosition(): int
    {
        return $this->localPosition ?? $this->sourcePosition;
    }

    public function transposedKey(): ?string
    {
        return $this->transposedKey;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * Applies incoming sync data. The local reorder override is dropped
     * once the upstream order actually changes — an override answering a
     * position that no longer exists is stale, not authoritative.
     */
    public function updateFromSync(int $sourcePosition, ?MusicalKey $transposedKey, ?string $notes): void
    {
        if ($this->sourcePosition !== $sourcePosition) {
            $this->localPosition = null;
        }

        $this->sourcePosition = $sourcePosition;
        $this->transposedKey = $transposedKey?->toString();
        $this->notes = $notes;
    }

    public function setLocalPosition(?int $position): void
    {
        $this->localPosition = $position;
    }
}
