<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Plugin\Bible;

final readonly class BibleTranslationSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $abbreviation,
        public string $language,
    ) {
    }
}
