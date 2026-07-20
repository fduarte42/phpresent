<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\ValueObject;

/**
 * What a `SlideDeck` was composed from. Only `Song` has a composer today
 * (`SlideComposer`) — SongSet/Bible/Media are listed per SDD §7's eventual
 * scope but have no composition pipeline yet, so adding those cases now
 * would be dead code.
 */
enum SlideSourceType: string
{
    case Song = 'song';
    case Blank = 'blank';
}
