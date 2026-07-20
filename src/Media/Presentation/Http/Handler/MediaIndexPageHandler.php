<?php

declare(strict_types=1);

namespace Phpresent\Media\Presentation\Http\Handler;

use Phpresent\Media\Application\Query\SearchMediaAssetsHandler;
use Phpresent\Media\Application\Query\SearchMediaAssetsQuery;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inertia page handler for the Media library. Same thin-handler shape as
 * `SongsIndexPageHandler`: an initial page of assets, re-fetched from
 * `/api/media` for search on the client.
 */
final readonly class MediaIndexPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private SearchMediaAssetsHandler $searchMediaAssetsHandler,
        private InertiaResponseFactory $inertia,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $assets = ($this->searchMediaAssetsHandler)(new SearchMediaAssetsQuery());

        return $this->inertia->render($request, 'Media/Index', [
            'assets' => $assets,
        ]);
    }
}
