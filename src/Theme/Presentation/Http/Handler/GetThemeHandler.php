<?php

declare(strict_types=1);

namespace Phpresent\Theme\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Theme\Application\Query\GetThemeHandler as GetThemeQueryHandler;
use Phpresent\Theme\Application\Query\GetThemeQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetThemeHandler implements RequestHandlerInterface
{
    public function __construct(private GetThemeQueryHandler $getThemeHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $theme = ($this->getThemeHandler)(new GetThemeQuery($id));

        if ($theme === null) {
            return new JsonResponse(['title' => 'Theme not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $theme]);
    }
}
