<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts between the ATOM-format timestamps `SongSourceInterface`/
 * `SongSetSourceInterface` speak (Application-facing) and the epoch-
 * milliseconds `BigInt` SongbookPro's `since`/`timestamp` fields use
 * (Infrastructure-facing, §6.2).
 *
 * The millisecond unit is an assumption, not a confirmed fact — the
 * reverse-engineering session in §6.1/§6.2 only observed single-page
 * (`hasMore: false`) responses, so no real `since`/`timestamp` pair was ever
 * captured to check against. Milliseconds (JS `Date.now()`-style) is the
 * overwhelmingly common convention for a JS/Apollo backend's `BigInt`
 * timestamp field, but this must be verified against real paginated traffic
 * before relying on it for anything more than "resume roughly from here."
 */
final class EpochMillis
{
    public static function fromAtom(string $atom): string
    {
        $dt = new DateTimeImmutable($atom);

        return (string) ($dt->getTimestamp() * 1000 + (int) $dt->format('v'));
    }

    public static function toAtom(string $epochMillis): string
    {
        $seconds = intdiv((int) $epochMillis, 1000);

        return (new DateTimeImmutable('@' . $seconds))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }
}
