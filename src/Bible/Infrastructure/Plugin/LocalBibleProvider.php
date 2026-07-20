<?php

declare(strict_types=1);

namespace Phpresent\Bible\Infrastructure\Plugin;

use Phpresent\Shared\Domain\Plugin\Bible\BiblePassageRecord;
use Phpresent\Shared\Domain\Plugin\Bible\BibleProviderInterface;
use Phpresent\Shared\Domain\Plugin\Bible\BibleTranslationSummary;
use Phpresent\Shared\Domain\Plugin\Bible\BibleVerseRecord;

/**
 * First real `BibleProviderInterface` plugin — see `KjvFixtureData`'s
 * docblock for why this is a small bundled fixture rather than a remote
 * API integration.
 */
final class LocalBibleProvider implements BibleProviderInterface
{
    private const string TRANSLATION_ID = 'kjv';

    public function id(): string
    {
        return 'local-kjv';
    }

    public function name(): string
    {
        return 'Local KJV Fixture';
    }

    public function translations(): array
    {
        return [
            new BibleTranslationSummary(
                id: self::TRANSLATION_ID,
                name: 'King James Version (fixture excerpt)',
                abbreviation: 'KJV',
                language: 'en',
            ),
        ];
    }

    public function search(string $translationId, string $query, int $limit = 20): array
    {
        if ($translationId !== self::TRANSLATION_ID || trim($query) === '') {
            return [];
        }

        $needle = mb_strtolower($query);
        $matches = [];

        foreach (KjvFixtureData::verses() as $book => $chapters) {
            foreach ($chapters as $chapter => $verses) {
                foreach ($verses as $verse => $text) {
                    if (!str_contains(mb_strtolower($text), $needle)) {
                        continue;
                    }

                    $matches[] = new BibleVerseRecord($book, $chapter, $verse, $text);

                    if (count($matches) >= $limit) {
                        return $matches;
                    }
                }
            }
        }

        return $matches;
    }

    public function getPassage(
        string $translationId,
        string $book,
        int $chapter,
        ?int $startVerse = null,
        ?int $endVerse = null,
    ): ?BiblePassageRecord {
        if ($translationId !== self::TRANSLATION_ID) {
            return null;
        }

        $verses = KjvFixtureData::verses()[$book][$chapter] ?? null;

        if ($verses === null) {
            return null;
        }

        $records = [];

        foreach ($verses as $verse => $text) {
            if ($startVerse !== null && $verse < $startVerse) {
                continue;
            }

            if ($endVerse !== null && $verse > $endVerse) {
                continue;
            }

            $records[] = new BibleVerseRecord($book, $chapter, $verse, $text);
        }

        if ($records === []) {
            return null;
        }

        return new BiblePassageRecord($book, $chapter, $records);
    }
}
