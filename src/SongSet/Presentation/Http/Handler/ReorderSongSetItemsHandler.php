<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\SongSet\Application\Command\ReorderSongSetItemsCommand;
use Phpresent\SongSet\Application\Command\ReorderSongSetItemsHandler as ReorderSongSetItemsCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Persists a local drag/drop item order. Local-only display state — never
 * calls out to SongbookPro (see docs/sdd.md §17.1).
 */
final readonly class ReorderSongSetItemsHandler implements RequestHandlerInterface
{
    public function __construct(private ReorderSongSetItemsCommandHandler $reorderSongSetItemsHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $body = $request->getParsedBody();
        $itemIds = is_array($body) && is_array($body['itemIds'] ?? null)
            ? array_map(static fn (mixed $itemId): string => (string) $itemId, $body['itemIds'])
            : [];

        $songSet = ($this->reorderSongSetItemsHandler)(new ReorderSongSetItemsCommand($id, $itemIds));

        if ($songSet === null) {
            return new JsonResponse(
                ['title' => 'Song set not found', 'status' => 404],
                404,
            );
        }

        return new JsonResponse(['data' => $songSet]);
    }
}
