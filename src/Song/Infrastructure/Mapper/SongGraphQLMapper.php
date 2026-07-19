<?php

declare(strict_types=1);

namespace Phpresent\Song\Infrastructure\Mapper;

use Phpresent\Song\Application\DTO\RemoteSongRecord;
use Phpresent\Song\Application\DTO\RemoteSongSectionRecord;
use Phpresent\Song\Domain\ValueObject\LyricFormat;
use Phpresent\Song\Domain\ValueObject\SectionType;

/**
 * Translates SongbookPro's GraphQL response shape into `RemoteSongRecord`
 * value objects. Isolated here so the GraphQL schema's field names/casing
 * never leak beyond this class.
 */
final class SongGraphQLMapper
{
    /**
     * @param array<string, mixed> $node one `songs.edges[].node` entry
     */
    public function mapSong(array $node): RemoteSongRecord
    {
        /** @var array<int, array<string, mixed>> $sectionNodes */
        $sectionNodes = $node['sections'] ?? [];

        return new RemoteSongRecord(
            externalId: (string) $node['id'],
            title: (string) $node['title'],
            authors: $this->stringList($node['authors'] ?? []),
            format: $this->mapFormat((string) ($node['format'] ?? 'plain_text')),
            revision: (string) ($node['revision'] ?? $node['updatedAt'] ?? '0'),
            checksum: (string) ($node['checksum'] ?? ''),
            sections: array_map($this->mapSection(...), $sectionNodes),
            copyright: isset($node['copyright']) ? (string) $node['copyright'] : null,
            ccli: isset($node['ccli']) ? (string) $node['ccli'] : null,
            defaultKey: isset($node['key']) ? (string) $node['key'] : null,
            tempo: isset($node['tempo']) ? (int) $node['tempo'] : null,
            capo: isset($node['capo']) ? (int) $node['capo'] : null,
            tags: $this->stringList($node['tags'] ?? []),
            metadata: is_array($node['metadata'] ?? null) ? $node['metadata'] : [],
        );
    }

    /**
     * @param array<string, mixed> $node one `sections[]` entry
     */
    private function mapSection(array $node): RemoteSongSectionRecord
    {
        return new RemoteSongSectionRecord(
            position: (int) $node['position'],
            type: $this->mapSectionType((string) ($node['type'] ?? 'custom')),
            content: (string) $node['content'],
            label: isset($node['label']) ? (string) $node['label'] : null,
            chordProSource: isset($node['chordProSource']) ? (string) $node['chordProSource'] : null,
        );
    }

    private function mapFormat(string $value): LyricFormat
    {
        return match (strtolower($value)) {
            'openlyrics', 'open_lyrics' => LyricFormat::OpenLyrics,
            'chordpro', 'chord_pro' => LyricFormat::ChordPro,
            default => LyricFormat::PlainText,
        };
    }

    private function mapSectionType(string $value): SectionType
    {
        return match (strtolower($value)) {
            'verse' => SectionType::Verse,
            'chorus' => SectionType::Chorus,
            'bridge' => SectionType::Bridge,
            'instrumental' => SectionType::Instrumental,
            'ending' => SectionType::Ending,
            'tag' => SectionType::Tag,
            'prechorus', 'pre_chorus' => SectionType::PreChorus,
            default => SectionType::Custom,
        };
    }

    /**
     * @return string[]
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($item): string => (string) $item, $value));
    }
}
