<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Phpresent\Presentation\Application\Query\ListDisplaysHandler;
use Phpresent\Presentation\Application\Query\ListDisplaysQuery;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inertia page handler for the Displays list. Renders `Displays/Index.vue`
 * with the current displays; the page itself calls `/api/displays` for
 * create/update/delete, same thin-handler shape as `SongSetsIndexPageHandler`.
 */
final readonly class DisplaysIndexPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListDisplaysHandler $listDisplaysHandler,
        private InertiaResponseFactory $inertia,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $displays = ($this->listDisplaysHandler)(new ListDisplaysQuery());

        return $this->inertia->render($request, 'Displays/Index', [
            'displays' => $displays,
        ]);
    }
}
