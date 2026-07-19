<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\ValueObject;

enum SectionType: string
{
    case Verse = 'verse';
    case Chorus = 'chorus';
    case Bridge = 'bridge';
    case Instrumental = 'instrumental';
    case Ending = 'ending';
    case Tag = 'tag';
    case PreChorus = 'pre_chorus';
    case Custom = 'custom';
}
