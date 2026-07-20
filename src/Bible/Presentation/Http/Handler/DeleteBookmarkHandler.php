<?php

declare(strict_types=1);

namespace Phpresent\Bible\Presentation\Http\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Bible\Application\Command\RemoveBookmarkCommand;
use Phpresent\Bible\Application\Command\RemoveBookmarkHandler as RemoveBookmarkCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DeleteBookmarkHandler implements RequestHandlerInterface
{
    public function __construct(private RemoveBookmarkCommandHandler $removeBookmarkHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $removed = ($this->removeBookmarkHandler)(new RemoveBookmarkCommand($id));

        if (!$removed) {
            return new JsonResponse(['title' => 'Bookmark not found', 'status' => 404], 404);
        }

        return new EmptyResponse();
    }
}
