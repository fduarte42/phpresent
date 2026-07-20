<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Phpresent\Presentation\Application\Query\GetPresentationSessionHandler;
use Phpresent\Presentation\Application\Query\GetPresentationSessionQuery;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Phpresent\Song\Application\Query\SearchSongsHandler;
use Phpresent\Song\Application\Query\SearchSongsQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inertia page handler for the live-control operator screen. Depending on
 * `Song\Application\Query\SearchSongsHandler` from this cross-module
 * Presentation page handler mirrors the same precedent already used inside
 * `SongSet\Application\Query\GetSongSetHandler` (SDD §7.2) — Application/
 * Presentation-layer cross-module dependencies are fine; only Domain-layer
 * ones are restricted (§17.1).
 *
 * `wsUrl` is computed here (not hardcoded in the frontend) from the
 * inbound request's host and the configured `websocket.port` — the
 * WebSocket server binds `websocket.host` (typically `0.0.0.0`, not
 * browser-reachable), so the browser needs the request's own host instead.
 */
final readonly class PresentationControlPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetPresentationSessionHandler $getPresentationSessionHandler,
        private SearchSongsHandler $searchSongsHandler,
        private InertiaResponseFactory $inertia,
        private int $websocketPort,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = ($this->getPresentationSessionHandler)(new GetPresentationSessionQuery());
        $songs = ($this->searchSongsHandler)(new SearchSongsQuery());

        $scheme = $request->getUri()->getScheme() === 'https' ? 'wss' : 'ws';
        $host = $request->getUri()->getHost();
        $wsUrl = sprintf('%s://%s:%d', $scheme, $host, $this->websocketPort);

        return $this->inertia->render($request, 'Presentation/Control', [
            'session' => $session,
            'songs' => $songs,
            'wsUrl' => $wsUrl,
        ]);
    }
}
