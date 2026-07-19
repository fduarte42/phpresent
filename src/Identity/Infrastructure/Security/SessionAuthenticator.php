<?php

declare(strict_types=1);

namespace Phpresent\Identity\Infrastructure\Security;

use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Phpresent\Identity\Application\Service\AuthenticatorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SessionAuthenticator implements AuthenticatorInterface
{
    public function authenticate(ServerRequestInterface $request): ?string
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        if (!$session instanceof SessionInterface) {
            return null;
        }

        $userId = $session->get('userId');

        return is_string($userId) ? $userId : null;
    }
}
