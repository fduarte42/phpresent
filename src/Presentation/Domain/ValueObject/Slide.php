<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\ValueObject;

/**
 * `sectionType` is a plain string (the source `SectionType` enum's
 * `->value`, e.g. `"verse"`), not `Song\Domain\ValueObject\SectionType`
 * itself — Presentation's Domain layer must not depend on another module's
 * Domain types, same rule that keeps `SongSetItem::songExternalId` a plain
 * string instead of a `Song` reference (SDD §17.1).
 */
final readonly class Slide
{
    /**
     * @param string[] $lines
     */
    public function __construct(
        public array $lines,
        public ?string $sectionType = null,
        public ?string $sectionLabel = null,
    ) {
    }

    /**
     * @return array{lines: string[], sectionType: ?string, sectionLabel: ?string}
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines,
            'sectionType' => $this->sectionType,
            'sectionLabel' => $this->sectionLabel,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<mixed> $rawLines */
        $rawLines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $sectionType = $data['sectionType'] ?? null;
        $sectionLabel = $data['sectionLabel'] ?? null;

        return new self(
            lines: array_values(array_map(
                static fn (mixed $line): string => is_scalar($line) ? (string) $line : '',
                $rawLines,
            )),
            sectionType: is_string($sectionType) ? $sectionType : null,
            sectionLabel: is_string($sectionLabel) ? $sectionLabel : null,
        );
    }
}
