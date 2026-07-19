<?php

declare(strict_types=1);

namespace Phpresent\Identity\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Phpresent\Identity\Application\Service\AuthenticatorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class JwtAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $secret,
        private string $algorithm = 'HS256',
    ) {
    }

    public function authenticate(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, strlen('Bearer '));

        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (Throwable) {
            // Any decode/signature/expiry failure means "not authenticated",
            // never a hard error — same contract as SessionAuthenticator.
            return null;
        }

        return isset($payload->sub) && is_string($payload->sub) ? $payload->sub : null;
    }
}
