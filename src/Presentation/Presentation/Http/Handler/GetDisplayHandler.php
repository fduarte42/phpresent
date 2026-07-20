<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Presentation\Application\Query\GetDisplayHandler as GetDisplayQueryHandler;
use Phpresent\Presentation\Application\Query\GetDisplayQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetDisplayHandler implements RequestHandlerInterface
{
    public function __construct(private GetDisplayQueryHandler $getDisplayHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $display = ($this->getDisplayHandler)(new GetDisplayQuery($id));

        if ($display === null) {
            return new JsonResponse(['title' => 'Display not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $display]);
    }
}
