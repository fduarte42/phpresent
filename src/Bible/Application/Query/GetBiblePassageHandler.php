<?php

declare(strict_types=1);

namespace Phpresent\Bible\Application\Query;

use Phpresent\Bible\Application\DTO\BiblePassageDto;
use Phpresent\Shared\Infrastructure\Plugin\PluginRegistry;

final readonly class GetBiblePassageHandler
{
    public function __construct(private PluginRegistry $pluginRegistry)
    {
    }

    public function __invoke(GetBiblePassageQuery $query): ?BiblePassageDto
    {
        foreach ($this->pluginRegistry->bibleProviders() as $provider) {
            $passage = $provider->getPassage(
                $query->translationId,
                $query->book,
                $query->chapter,
                $query->startVerse,
                $query->endVerse,
            );

            if ($passage !== null) {
                return BiblePassageDto::fromRecord($passage);
            }
        }

        return null;
    }
}
