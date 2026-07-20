<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\Security;

use Phpresent\SongbookPro\Domain\Exception\SongbookProApiException;
use Phpresent\SongbookPro\Domain\Service\AccessTokenProviderInterface;

/**
 * Reads a pre-obtained, out-of-band bearer token from config
 * (`songbookpro.api_token`). See `AccessTokenProviderInterface` for why this
 * — not a real B2C grant — is the current implementation.
 */
final readonly class StaticAccessTokenProvider implements AccessTokenProviderInterface
{
    public function __construct(private string $token)
    {
    }

    public function getAccessToken(): string
    {
        if ($this->token === '') {
            throw new SongbookProApiException(
                'No SongbookPro API token configured (songbookpro.api_token / SONGBOOKPRO_API_TOKEN).',
            );
        }

        return $this->token;
    }
}
