<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\SongSet\Application\Query\GetSongSetHandler as GetSongSetQueryHandler;
use Phpresent\SongSet\Application\Query\GetSongSetQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetSongSetHandler implements RequestHandlerInterface
{
    public function __construct(private GetSongSetQueryHandler $getSongSetHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $songSet = ($this->getSongSetHandler)(new GetSongSetQuery($id));

        if ($songSet === null) {
            return new JsonResponse(
                ['title' => 'Song set not found', 'status' => 404],
                404,
            );
        }

        return new JsonResponse(['data' => $songSet]);
    }
}
