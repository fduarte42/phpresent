<?php

declare(strict_types=1);

namespace Phpresent\Shared\Presentation\Http\Handler;

use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The `/` landing page — a static grid of links to every admin area.
 * Deliberately doesn't fetch cross-module counts/stats (song count, active
 * displays, ...): that's a real feature with its own design, not something
 * to bolt on as a side effect of finally giving the app a home page.
 */
final readonly class DashboardPageHandler implements RequestHandlerInterface
{
    public function __construct(private InertiaResponseFactory $inertia)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inertia->render($request, 'Dashboard');
    }
}
