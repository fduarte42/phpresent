<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Domain\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Phpresent\SongSet\Domain\Exception\UnknownSongSetItemException;
use Phpresent\SongSet\Domain\ValueObject\MusicalKey;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'song_sets')]
#[ORM\Index(columns: ['name'], name: 'idx_song_sets_name')]
class SongSet
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'external_id', type: 'string', length: 191, unique: true)]
    private string $externalId;

    #[ORM\Column(type: 'string', length: 512)]
    private string $name;

    #[ORM\Column(name: 'service_date', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $serviceDate;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

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

    /** @var Collection<int, SongSetItem> */
    #[ORM\OneToMany(targetEntity: SongSetItem::class, mappedBy: 'songSet', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(
        string $externalId,
        string $name,
        string $sourceRevision,
        string $sourceChecksum,
        ?DateTimeImmutable $serviceDate = null,
        ?string $notes = null,
        ?DateTimeImmutable $now = null,
    ) {
        $now ??= new DateTimeImmutable();

        $this->id = Uuid::uuid4();
        $this->externalId = $externalId;
        $this->name = $name;
        $this->serviceDate = $serviceDate;
        $this->notes = $notes;
        $this->sourceRevision = $sourceRevision;
        $this->sourceChecksum = $sourceChecksum;
        $this->syncedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->items = new ArrayCollection();
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function serviceDate(): ?DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function notes(): ?string
    {
        return $this->notes;
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
     * Items ordered by their effective position (local override, if any,
     * else SongbookPro's own order). effectivePosition() is computed, not
     * a mapped column, so ordering happens in memory rather than via a
     * Doctrine Criteria (see SongSection::sections() in the Song module for
     * the mapped-column equivalent).
     *
     * @return list<SongSetItem>
     */
    public function items(): array
    {
        $items = $this->items->toArray();
        usort($items, static fn (SongSetItem $a, SongSetItem $b): int => $a->effectivePosition() <=> $b->effectivePosition());

        return array_values($items);
    }

    public function hasDiverged(string $sourceRevision, string $sourceChecksum): bool
    {
        return $this->sourceRevision !== $sourceRevision || $this->sourceChecksum !== $sourceChecksum;
    }

    public function applySync(
        string $name,
        string $sourceRevision,
        string $sourceChecksum,
        ?DateTimeImmutable $serviceDate,
        ?string $notes,
        DateTimeImmutable $now,
    ): void {
        $this->name = $name;
        $this->serviceDate = $serviceDate;
        $this->notes = $notes;
        $this->sourceRevision = $sourceRevision;
        $this->sourceChecksum = $sourceChecksum;
        $this->syncedAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Merges incoming sync items against what's already here, matched by
     * songExternalId: existing items are updated in place (preserving
     * localPosition unless sourcePosition changed, see
     * SongSetItem::updateFromSync()), items no longer present upstream are
     * removed, and new items are appended. This is deliberately not a
     * clear-and-replace like Song::replaceSections() — SongSetItem carries
     * local-only state (localPosition) that a sync pass must not discard
     * wholesale.
     *
     * @param list<array{songExternalId: string, sourcePosition: int, transposedKey: ?MusicalKey, notes: ?string}> $records
     */
    public function syncItems(array $records): void
    {
        $incomingIds = array_map(static fn (array $record): string => $record['songExternalId'], $records);

        foreach ($this->items->toArray() as $existing) {
            if (!in_array($existing->songExternalId(), $incomingIds, true)) {
                $this->items->removeElement($existing);
            }
        }

        foreach ($records as $record) {
            $existing = $this->findItemByExternalId($record['songExternalId']);

            if ($existing === null) {
                $this->items->add(new SongSetItem(
                    songSet: $this,
                    songExternalId: $record['songExternalId'],
                    sourcePosition: $record['sourcePosition'],
                    transposedKey: $record['transposedKey'],
                    notes: $record['notes'],
                ));

                continue;
            }

            $existing->updateFromSync($record['sourcePosition'], $record['transposedKey'], $record['notes']);
        }
    }

    /**
     * Applies a local drag/drop reorder. Purely local display state — never
     * mutates sourcePosition and never calls out to SongbookPro.
     *
     * @param list<string> $orderedItemIds
     */
    public function reorder(array $orderedItemIds): void
    {
        foreach ($orderedItemIds as $index => $itemId) {
            $item = $this->findItemById($itemId);

            if ($item === null) {
                throw UnknownSongSetItemException::forId($itemId);
            }

            $item->setLocalPosition($index);
        }
    }

    private function findItemByExternalId(string $songExternalId): ?SongSetItem
    {
        foreach ($this->items as $item) {
            if ($item->songExternalId() === $songExternalId) {
                return $item;
            }
        }

        return null;
    }

    private function findItemById(string $itemId): ?SongSetItem
    {
        foreach ($this->items as $item) {
            if ($item->id()->toString() === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
