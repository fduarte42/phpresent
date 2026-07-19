<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phpresent\Song\Domain\ValueObject\SectionType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'song_sections')]
#[ORM\UniqueConstraint(name: 'uniq_song_position', columns: ['song_id', 'position'])]
#[ORM\Index(columns: ['song_id'], name: 'idx_song_sections_song_id')]
class SongSection
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Song::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(name: 'song_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Song $song;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(type: 'string', length: 24, enumType: SectionType::class)]
    private SectionType $type;

    #[ORM\Column(type: 'string', length: 191, nullable: true)]
    private ?string $label;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(name: 'chordpro_source', type: 'text', nullable: true)]
    private ?string $chordProSource;

    public function __construct(
        Song $song,
        int $position,
        SectionType $type,
        string $content,
        ?string $label = null,
        ?string $chordProSource = null,
    ) {
        $this->id = Uuid::uuid4();
        $this->song = $song;
        $this->position = $position;
        $this->type = $type;
        $this->content = $content;
        $this->label = $label;
        $this->chordProSource = $chordProSource;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function type(): SectionType
    {
        return $this->type;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function chordProSource(): ?string
    {
        return $this->chordProSource;
    }

    /**
     * Replaces this section's content in place during a sync pass.
     * Position is intentionally not re-derived here — the caller (sync
     * service) is the only place allowed to assign position, straight from
     * SongbookPro's own ordering.
     */
    public function updateContent(SectionType $type, string $content, ?string $label, ?string $chordProSource): void
    {
        $this->type = $type;
        $this->content = $content;
        $this->label = $label;
        $this->chordProSource = $chordProSource;
    }
}
