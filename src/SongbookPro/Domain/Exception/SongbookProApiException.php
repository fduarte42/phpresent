<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Domain\Exception;

use RuntimeException;
use Throwable;

final class SongbookProApiException extends RuntimeException
{
    /** @param array<int, array{message: string}> $errors */
    public static function fromGraphQLErrors(array $errors): self
    {
        $messages = array_map(static fn (array $error): string => $error['message'], $errors);

        return new self('SongbookPro GraphQL API returned errors: ' . implode('; ', $messages));
    }

    public static function fromTransportFailure(Throwable $previous): self
    {
        return new self('Failed to reach SongbookPro GraphQL API: ' . $previous->getMessage(), 0, $previous);
    }

    public static function rateLimited(): self
    {
        return new self('SongbookPro GraphQL API rate limit exceeded.');
    }
}
