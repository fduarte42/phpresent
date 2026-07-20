<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Renders the login form. `POST /login` (`LoginHandler`) already existed
 * as a REST-style JSON endpoint (§18.4) — nothing rendered a page a human
 * could actually submit from a browser until now.
 */
final readonly class LoginPageHandler implements RequestHandlerInterface
{
    public function __construct(private InertiaResponseFactory $inertia)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inertia->render($request, 'Auth/Login');
    }
}
