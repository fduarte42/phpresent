<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Query;

use Phpresent\Bible\Application\DTO\BibleTranslationDto;
use Phpresent\Shared\Infrastructure\Plugin\PluginRegistry;

/**
 * Merges `translations()` across every registered `BibleProviderInterface`
 * plugin (SDD §12) — there is no single "the" translation list, since more
 * than one provider can be registered at once.
 */
final readonly class ListBibleTranslationsHandler
{
    public function __construct(private PluginRegistry $pluginRegistry)
    {
    }

    /**
     * @return list<BibleTranslationDto>
     */
    public function __invoke(ListBibleTranslationsQuery $query): array
    {
        $translations = [];

        foreach ($this->pluginRegistry->bibleProviders() as $provider) {
            foreach ($provider->translations() as $summary) {
                $translations[] = BibleTranslationDto::fromRecord($provider->id(), $summary);
            }
        }

        return $translations;
    }
}
