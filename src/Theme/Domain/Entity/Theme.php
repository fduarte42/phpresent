<?php

declare(strict_types=1);

namespace Phpresent\Theme\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phpresent\Theme\Domain\Exception\InvalidThemeScopeException;
use Phpresent\Theme\Domain\ValueObject\TextAlign;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * A visual style, at one of three scopes (SDD §2/§19: "global / song /
 * section scoped"), with increasing specificity — a Section-scoped theme
 * is meant to override a Song-scoped theme for slides of that section
 * type, which in turn overrides the Global default. This increment only
 * implements storage/CRUD for themes; nothing yet resolves that
 * precedence during rendering — see the module's SDD section for why.
 *
 * `songExternalId`/`sectionType` are plain strings, not `Song\Domain\
 * Entity\Song`/`Song\Domain\ValueObject\SectionType` — same cross-module
 * Domain-dependency rule that keeps `SongSetItem::songExternalId` and
 * `Presentation\Domain\ValueObject\Slide::sectionType` plain strings too.
 * Likewise `backgroundMediaAssetId` is a plain string id into the Media
 * module, not a `MediaAsset` reference — and, like `SongSetItem` allowing
 * a `songExternalId` for a song that isn't synced yet, nothing here
 * requires the referenced asset to actually exist.
 */
#[ORM\Entity]
#[ORM\Table(name: 'themes')]
class Theme
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 191)]
    private string $name;

    #[ORM\Column(type: 'string', length: 16, enumType: ThemeScope::class)]
    private ThemeScope $scope;

    #[ORM\Column(name: 'song_external_id', type: 'string', length: 191, nullable: true)]
    private ?string $songExternalId;

    #[ORM\Column(name: 'section_type', type: 'string', length: 24, nullable: true)]
    private ?string $sectionType;

    #[ORM\Column(name: 'background_color', type: 'string', length: 16, nullable: true)]
    private ?string $backgroundColor;

    #[ORM\Column(name: 'background_media_asset_id', type: 'string', length: 191, nullable: true)]
    private ?string $backgroundMediaAssetId;

    #[ORM\Column(name: 'font_family', type: 'string', length: 191, nullable: true)]
    private ?string $fontFamily;

    #[ORM\Column(name: 'font_color', type: 'string', length: 16, nullable: true)]
    private ?string $fontColor;

    #[ORM\Column(name: 'font_size_scale', type: 'float')]
    private float $fontSizeScale;

    #[ORM\Column(name: 'text_align', type: 'string', length: 8, enumType: TextAlign::class)]
    private TextAlign $textAlign;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        ThemeScope $scope,
        ?string $songExternalId = null,
        ?string $sectionType = null,
        ?string $backgroundColor = null,
        ?string $backgroundMediaAssetId = null,
        ?string $fontFamily = null,
        ?string $fontColor = null,
        float $fontSizeScale = 1.0,
        TextAlign $textAlign = TextAlign::Center,
        ?DateTimeImmutable $now = null,
    ) {
        self::assertValidTarget($scope, $songExternalId, $sectionType);

        $now ??= new DateTimeImmutable();

        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->scope = $scope;
        $this->songExternalId = $songExternalId;
        $this->sectionType = $sectionType;
        $this->backgroundColor = $backgroundColor;
        $this->backgroundMediaAssetId = $backgroundMediaAssetId;
        $this->fontFamily = $fontFamily;
        $this->fontColor = $fontColor;
        $this->fontSizeScale = $fontSizeScale;
        $this->textAlign = $textAlign;
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

    public function scope(): ThemeScope
    {
        return $this->scope;
    }

    public function songExternalId(): ?string
    {
        return $this->songExternalId;
    }

    public function sectionType(): ?string
    {
        return $this->sectionType;
    }

    public function backgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function backgroundMediaAssetId(): ?string
    {
        return $this->backgroundMediaAssetId;
    }

    public function fontFamily(): ?string
    {
        return $this->fontFamily;
    }

    public function fontColor(): ?string
    {
        return $this->fontColor;
    }

    public function fontSizeScale(): float
    {
        return $this->fontSizeScale;
    }

    public function textAlign(): TextAlign
    {
        return $this->textAlign;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        string $name,
        ThemeScope $scope,
        ?string $songExternalId,
        ?string $sectionType,
        ?string $backgroundColor,
        ?string $backgroundMediaAssetId,
        ?string $fontFamily,
        ?string $fontColor,
        float $fontSizeScale,
        TextAlign $textAlign,
        DateTimeImmutable $now,
    ): void {
        self::assertValidTarget($scope, $songExternalId, $sectionType);

        $this->name = $name;
        $this->scope = $scope;
        $this->songExternalId = $songExternalId;
        $this->sectionType = $sectionType;
        $this->backgroundColor = $backgroundColor;
        $this->backgroundMediaAssetId = $backgroundMediaAssetId;
        $this->fontFamily = $fontFamily;
        $this->fontColor = $fontColor;
        $this->fontSizeScale = $fontSizeScale;
        $this->textAlign = $textAlign;
        $this->updatedAt = $now;
    }

    private static function assertValidTarget(ThemeScope $scope, ?string $songExternalId, ?string $sectionType): void
    {
        if ($scope === ThemeScope::Global && ($songExternalId !== null || $sectionType !== null)) {
            throw InvalidThemeScopeException::targetNotAllowed($scope);
        }

        if ($scope === ThemeScope::Song) {
            if ($songExternalId === null) {
                throw InvalidThemeScopeException::songExternalIdRequired();
            }

            if ($sectionType !== null) {
                throw InvalidThemeScopeException::targetNotAllowed($scope);
            }
        }

        if ($scope === ThemeScope::Section) {
            if ($sectionType === null) {
                throw InvalidThemeScopeException::sectionTypeRequired();
            }

            if ($songExternalId !== null) {
                throw InvalidThemeScopeException::targetNotAllowed($scope);
            }
        }
    }
}
