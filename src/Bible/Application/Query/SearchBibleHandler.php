<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Query;

use Phpresent\Bible\Application\DTO\BibleVerseDto;
use Phpresent\Shared\Infrastructure\Plugin\PluginRegistry;

/**
 * Asks every registered provider to search `translationId` — only the
 * provider that actually owns that translation id will return anything
 * (each provider is responsible for recognizing its own ids and returning
 * an empty result otherwise, per `BibleProviderInterface`'s contract), so
 * no separate translationId-to-provider routing table is needed here.
 */
final readonly class SearchBibleHandler
{
    public function __construct(private PluginRegistry $pluginRegistry)
    {
    }

    /**
     * @return list<BibleVerseDto>
     */
    public function __invoke(SearchBibleQuery $query): array
    {
        foreach ($this->pluginRegistry->bibleProviders() as $provider) {
            $results = $provider->search($query->translationId, $query->query, $query->limit);

            if ($results !== []) {
                return array_map(BibleVerseDto::fromRecord(...), $results);
            }
        }

        return [];
    }
}
