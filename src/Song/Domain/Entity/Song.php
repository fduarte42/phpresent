<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Phpresent\Song\Domain\ValueObject\CcliNumber;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\MusicalKey;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'songs')]
#[ORM\Index(columns: ['title'], name: 'idx_songs_title')]
class Song
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'external_id', type: 'string', length: 191, unique: true)]
    private string $externalId;

    #[ORM\Column(type: 'string', length: 512)]
    private string $title;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $authors;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $copyright;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $ccli;

    #[ORM\Column(name: 'default_key', type: 'string', length: 8, nullable: true)]
    private ?string $defaultKey;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $tempo;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $capo;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $tags;

    #[ORM\Column(type: 'string', length: 16, enumType: LyricFormat::class)]
    private LyricFormat $format;

    /** @var array<string, scalar|array<mixed>|null> */
    #[ORM\Column(type: 'json')]
    private array $metadata;

    #[ORM\Column(name: 'source_revision', type: 'string', length: 191)]
    private string $sourceRevision;

    #[ORM\Column(name: 'source_checksum', type: 'string', length: 191)]
    private string $sourceChecksum;

    #[ORM\Column(name: 'synced_at', type: 'datetime_immutable')]
    private DateTimeImmutable $syncedAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, SongSection> */
    #[ORM\OneToMany(targetEntity: SongSection::class, mappedBy: 'song', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $sections;

    /**
     * @param string[] $authors
     * @param string[] $tags
     * @param array<string, scalar|array<mixed>|null> $metadata
     */
    public function __construct(
        string $externalId,
        string $title,
        array $authors,
        LyricFormat $format,
        string $sourceRevision,
        string $sourceChecksum,
        ?string $copyright = null,
        ?CcliNumber $ccli = null,
        ?MusicalKey $defaultKey = null,
        ?int $tempo = null,
        ?int $capo = null,
        array $tags = [],
        array $metadata = [],
        ?DateTimeImmutable $now = null,
    ) {
        $now ??= new DateTimeImmutable();

        $this->id = Uuid::uuid4();
        $this->externalId = $externalId;
        $this->title = $title;
        $this->authors = $authors;
        $this->copyright = $copyright;
        $this->ccli = $ccli?->toString();
        $this->defaultKey = $defaultKey?->toString();
        $this->tempo = $tempo;
        $this->capo = $capo;
        $this->tags = $tags;
        $this->format = $format;
        $this->metadata = $metadata;
        $this->sourceRevision = $sourceRevision;
        $this->sourceChecksum = $sourceChecksum;
        $this->syncedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->sections = new ArrayCollection();
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function title(): string
    {
        return $this->title;
    }

    /** @return string[] */
    public function authors(): array
    {
        return $this->authors;
    }

    public function copyright(): ?string
    {
        return $this->copyright;
    }

    public function ccli(): ?string
    {
        return $this->ccli;
    }

    public function defaultKey(): ?string
    {
        return $this->defaultKey;
    }

    public function tempo(): ?int
    {
        return $this->tempo;
    }

    public function capo(): ?int
    {
        return $this->capo;
    }

    /** @return string[] */
    public function tags(): array
    {
        return $this->tags;
    }

    public function format(): LyricFormat
    {
        return $this->format;
    }

    /** @return array<string, scalar|array<mixed>|null> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function sourceRevision(): string
    {
        return $this->sourceRevision;
    }

    public function sourceChecksum(): string
    {
        return $this->sourceChecksum;
    }

    public function syncedAt(): DateTimeImmutable
    {
        return $this->syncedAt;
    }

    /**
     * Sections ordered exactly as SongbookPro provided them. Never re-sorted
     * by any heuristic other than the stored position.
     *
     * @return list<SongSection>
     */
    public function sections(): array
    {
        $criteria = Criteria::create()->orderBy(['position' => Criteria::ASC]);

        return array_values($this->sections->matching($criteria)->toArray());
    }

    /**
     * Whether the given upstream revision/checksum differs from what we
     * have stored — i.e. SongbookPro has changed this song since our last
     * sync.
     */
    public function hasDiverged(string $sourceRevision, string $sourceChecksum): bool
    {
        return $this->sourceRevision !== $sourceRevision || $this->sourceChecksum !== $sourceChecksum;
    }

    /**
     * @param string[] $authors
     * @param string[] $tags
     * @param array<string, scalar|array<mixed>|null> $metadata
     */
    public function applySync(
        string $title,
        array $authors,
        LyricFormat $format,
        string $sourceRevision,
        string $sourceChecksum,
        ?string $copyright,
        ?CcliNumber $ccli,
        ?MusicalKey $defaultKey,
        ?int $tempo,
        ?int $capo,
        array $tags,
        array $metadata,
        DateTimeImmutable $now,
    ): void {
        $this->title = $title;
        $this->authors = $authors;
        $this->format = $format;
        $this->sourceRevision = $sourceRevision;
        $this->sourceChecksum = $sourceChecksum;
        $this->copyright = $copyright;
        $this->ccli = $ccli?->toString();
        $this->defaultKey = $defaultKey?->toString();
        $this->tempo = $tempo;
        $this->capo = $capo;
        $this->tags = $tags;
        $this->metadata = $metadata;
        $this->syncedAt = $now;
        $this->updatedAt = $now;
    }

    public function replaceSections(SongSection ...$sections): void
    {
        $this->sections->clear();
        foreach ($sections as $section) {
            $this->sections->add($section);
        }
    }
}
