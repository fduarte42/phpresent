<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Domain\Exception;

use InvalidArgumentException;

final class UnknownSongSetItemException extends InvalidArgumentException
{
    public static function forId(string $itemId): self
    {
        return new self(sprintf('Item "%s" does not belong to this song set.', $itemId));
    }
}
