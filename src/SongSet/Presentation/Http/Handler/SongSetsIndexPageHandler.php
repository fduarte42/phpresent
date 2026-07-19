<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Presentation\Http\Handler;

use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Phpresent\SongSet\Application\Query\SearchSongSetsHandler;
use Phpresent\SongSet\Application\Query\SearchSongSetsQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inertia page handler for the Song Sets list. Renders
 * `SongSets/Index.vue` with an initial page of sets; the page itself
 * re-fetches from `/api/songsets` for search/pagination so this handler
 * stays a thin composition of query + Inertia render.
 */
final readonly class SongSetsIndexPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private SearchSongSetsHandler $searchSongSetsHandler,
        private InertiaResponseFactory $inertia,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $songSets = ($this->searchSongSetsHandler)(new SearchSongSetsQuery());

        return $this->inertia->render($request, 'SongSets/Index', [
            'songSets' => $songSets,
        ]);
    }
}
