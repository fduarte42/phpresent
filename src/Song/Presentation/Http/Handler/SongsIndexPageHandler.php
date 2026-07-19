<?php

declare(strict_types=1);

namespace Phpresent\Song\Presentation\Http\Handler;

use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Phpresent\Song\Application\Query\SearchSongsHandler;
use Phpresent\Song\Application\Query\SearchSongsQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inertia page handler for the Songs list. Renders `Songs/Index.vue` with
 * an initial page of songs; the page itself re-fetches from
 * `/api/songs` for search/pagination so this handler stays a thin
 * composition of query + Inertia render.
 */
final readonly class SongsIndexPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private SearchSongsHandler $searchSongsHandler,
        private InertiaResponseFactory $inertia,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $songs = ($this->searchSongsHandler)(new SearchSongsQuery());

        return $this->inertia->render($request, 'Songs/Index', [
            'songs' => $songs,
        ]);
    }
}
