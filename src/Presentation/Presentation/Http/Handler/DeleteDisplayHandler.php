<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Presentation\Application\Command\RemoveDisplayCommand;
use Phpresent\Presentation\Application\Command\RemoveDisplayHandler as RemoveDisplayCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DeleteDisplayHandler implements RequestHandlerInterface
{
    public function __construct(private RemoveDisplayCommandHandler $removeDisplayHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $removed = ($this->removeDisplayHandler)(new RemoveDisplayCommand($id));

        if (!$removed) {
            return new JsonResponse(['title' => 'Display not found', 'status' => 404], 404);
        }

        return new EmptyResponse();
    }
}
