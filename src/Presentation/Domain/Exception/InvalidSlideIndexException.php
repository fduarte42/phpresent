<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\Exception;

use InvalidArgumentException;

final class InvalidSlideIndexException extends InvalidArgumentException
{
    public static function forIndex(int $index, int $slideCount): self
    {
        return new self(sprintf('Slide index %d is out of range (deck has %d slide(s)).', $index, $slideCount));
    }
}
