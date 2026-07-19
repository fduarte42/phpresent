<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Middleware;

use Phpresent\Identity\Application\Service\AuthenticatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the current actor and attaches it as the `actorUserId` request
 * attribute. Never rejects a request itself — permission enforcement is an
 * Application-handler concern via PermissionInterface (docs/sdd.md §18.4).
 */
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    public const string ACTOR_ATTRIBUTE = 'actorUserId';

    public function __construct(private AuthenticatorInterface $authenticator)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actorUserId = $this->authenticator->authenticate($request);

        return $handler->handle($request->withAttribute(self::ACTOR_ATTRIBUTE, $actorUserId));
    }
}
