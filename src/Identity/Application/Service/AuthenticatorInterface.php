<?php

declare(strict_types=1);

namespace Phpresent\Identity\Application\Service;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the authenticated user id (if any) from a request, without
 * ever rejecting the request itself — permission enforcement happens in
 * Application handlers via PermissionInterface, not here (docs/sdd.md
 * §18.2/§18.4).
 */
interface AuthenticatorInterface
{
    public function authenticate(ServerRequestInterface $request): ?string;
}
