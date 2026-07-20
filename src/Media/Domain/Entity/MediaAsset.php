<?php

declare(strict_types=1);

namespace Phpresent\Media\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phpresent\Media\Domain\ValueObject\MediaKind;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * An uploaded asset (SDD §2's "images/video/audio assets"). Assets are
 * immutable once uploaded — there is no `update()`; replacing content means
 * uploading a new asset and removing the old one, so `storageKey` never
 * needs to change out from under anything already referencing it.
 *
 * `width`/`height` are only ever populated for images (extracted from the
 * actual file bytes at upload time, `FlysystemMediaStorage::write()`) —
 * video/audio dimensions/duration would need a media-inspection library
 * this project doesn't have, so those fields are left out entirely rather
 * than added as permanently-null columns.
 */
#[ORM\Entity]
#[ORM\Table(name: 'media_assets')]
#[ORM\Index(columns: ['filename'], name: 'idx_media_assets_filename')]
class MediaAsset
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $filename;

    #[ORM\Column(name: 'storage_key', type: 'string', length: 512, unique: true)]
    private string $storageKey;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 191)]
    private string $mimeType;

    #[ORM\Column(name: 'size_bytes', type: 'integer')]
    private int $sizeBytes;

    #[ORM\Column(type: 'string', length: 16, enumType: MediaKind::class)]
    private MediaKind $kind;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $width;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $height;

    #[ORM\Column(name: 'uploaded_at', type: 'datetime_immutable')]
    private DateTimeImmutable $uploadedAt;

    public function __construct(
        string $filename,
        string $storageKey,
        string $mimeType,
        int $sizeBytes,
        ?int $width = null,
        ?int $height = null,
        ?DateTimeImmutable $now = null,
    ) {
        $this->id = Uuid::uuid4();
        $this->filename = $filename;
        $this->storageKey = $storageKey;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->kind = MediaKind::fromMimeType($mimeType);
        $this->width = $width;
        $this->height = $height;
        $this->uploadedAt = $now ?? new DateTimeImmutable();
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function kind(): MediaKind
    {
        return $this->kind;
    }

    public function width(): ?int
    {
        return $this->width;
    }

    public function height(): ?int
    {
        return $this->height;
    }

    public function uploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
