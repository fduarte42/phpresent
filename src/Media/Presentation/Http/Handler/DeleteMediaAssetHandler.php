<?php

declare(strict_types=1);

namespace Phpresent\Media\Presentation\Http\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Media\Application\Command\RemoveMediaAssetCommand;
use Phpresent\Media\Application\Command\RemoveMediaAssetHandler as RemoveMediaAssetCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DeleteMediaAssetHandler implements RequestHandlerInterface
{
    public function __construct(private RemoveMediaAssetCommandHandler $removeMediaAssetHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $removed = ($this->removeMediaAssetHandler)(new RemoveMediaAssetCommand($id));

        if (!$removed) {
            return new JsonResponse(['title' => 'Media asset not found', 'status' => 404], 404);
        }

        return new EmptyResponse();
    }
}
