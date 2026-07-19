<?php

declare(strict_types=1);

namespace Phpresent\Identity\Infrastructure\Security;

use Phpresent\Identity\Application\Service\AuthenticatorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session first, always; JWT bearer only as a fallback for `/api/*`
 * requests (docs/sdd.md §8/§18.2). Bound to AuthenticatorInterface in DI.
 */
final readonly class CompositeAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private SessionAuthenticator $sessionAuthenticator,
        private JwtAuthenticator $jwtAuthenticator,
    ) {
    }

    public function authenticate(ServerRequestInterface $request): ?string
    {
        $userId = $this->sessionAuthenticator->authenticate($request);

        if ($userId !== null) {
            return $userId;
        }

        if (!str_starts_with($request->getUri()->getPath(), '/api/')) {
            return null;
        }

        return $this->jwtAuthenticator->authenticate($request);
    }
}
