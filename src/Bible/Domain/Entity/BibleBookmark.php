<?php

declare(strict_types=1);

namespace Phpresent\Bible\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * A saved reference to a passage for quick recall during a service —
 * Phpresent never stores the scripture text itself (that always comes
 * from a `BibleProviderInterface` plugin, §12); this is just a pointer
 * (translation + book + chapter + verse range) plus an optional label.
 * Immutable once created — same reasoning as `MediaAsset` (§19): there's
 * no partial-edit use case, remove and re-create instead.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bible_bookmarks')]
class BibleBookmark
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'translation_id', type: 'string', length: 64)]
    private string $translationId;

    #[ORM\Column(type: 'string', length: 191)]
    private string $book;

    #[ORM\Column(type: 'integer')]
    private int $chapter;

    #[ORM\Column(name: 'start_verse', type: 'integer', nullable: true)]
    private ?int $startVerse;

    #[ORM\Column(name: 'end_verse', type: 'integer', nullable: true)]
    private ?int $endVerse;

    #[ORM\Column(type: 'string', length: 191, nullable: true)]
    private ?string $label;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $translationId,
        string $book,
        int $chapter,
        ?int $startVerse = null,
        ?int $endVerse = null,
        ?string $label = null,
        ?DateTimeImmutable $now = null,
    ) {
        $this->id = Uuid::uuid4();
        $this->translationId = $translationId;
        $this->book = $book;
        $this->chapter = $chapter;
        $this->startVerse = $startVerse;
        $this->endVerse = $endVerse;
        $this->label = $label;
        $this->createdAt = $now ?? new DateTimeImmutable();
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function translationId(): string
    {
        return $this->translationId;
    }

    public function book(): string
    {
        return $this->book;
    }

    public function chapter(): int
    {
        return $this->chapter;
    }

    public function startVerse(): ?int
    {
        return $this->startVerse;
    }

    public function endVerse(): ?int
    {
        return $this->endVerse;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
