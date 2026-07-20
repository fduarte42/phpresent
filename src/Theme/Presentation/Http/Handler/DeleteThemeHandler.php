<?php

declare(strict_types=1);

namespace Phpresent\Theme\Presentation\Http\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Theme\Application\Command\RemoveThemeCommand;
use Phpresent\Theme\Application\Command\RemoveThemeHandler as RemoveThemeCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DeleteThemeHandler implements RequestHandlerInterface
{
    public function __construct(private RemoveThemeCommandHandler $removeThemeHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $removed = ($this->removeThemeHandler)(new RemoveThemeCommand($id));

        if (!$removed) {
            return new JsonResponse(['title' => 'Theme not found', 'status' => 404], 404);
        }

        return new EmptyResponse();
    }
}
