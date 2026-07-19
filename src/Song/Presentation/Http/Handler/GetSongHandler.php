<?php

declare(strict_types=1);

namespace Phpresent\Song\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Song\Application\Query\GetSongHandler as GetSongQueryHandler;
use Phpresent\Song\Application\Query\GetSongQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetSongHandler implements RequestHandlerInterface
{
    public function __construct(private GetSongQueryHandler $getSongHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $song = ($this->getSongHandler)(new GetSongQuery($id));

        if ($song === null) {
            return new JsonResponse(
                ['title' => 'Song not found', 'status' => 404],
                404,
            );
        }

        return new JsonResponse(['data' => $song]);
    }
}
