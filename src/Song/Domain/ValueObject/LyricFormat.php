<?php

declare(strict_types=1);

namespace Phpresent\Song\Domain\ValueObject;

enum LyricFormat: string
{
    case OpenLyrics = 'open_lyrics';
    case ChordPro = 'chord_pro';
    case PlainText = 'plain_text';
}
