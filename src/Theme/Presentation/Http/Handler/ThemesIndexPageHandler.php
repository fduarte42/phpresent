<?php

declare(strict_types=1);

namespace Phpresent\Theme\Presentation\Http\Handler;

use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Phpresent\Theme\Application\Query\ListThemesHandler;
use Phpresent\Theme\Application\Query\ListThemesQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inertia page handler for the Themes list, same thin-handler shape as
 * `DisplaysIndexPageHandler`.
 */
final readonly class ThemesIndexPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListThemesHandler $listThemesHandler,
        private InertiaResponseFactory $inertia,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $themes = ($this->listThemesHandler)(new ListThemesQuery());

        return $this->inertia->render($request, 'Themes/Index', [
            'themes' => $themes,
        ]);
    }
}
