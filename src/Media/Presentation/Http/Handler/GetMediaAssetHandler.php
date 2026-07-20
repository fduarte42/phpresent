<?php

declare(strict_types=1);

namespace Phpresent\Media\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Media\Application\Query\GetMediaAssetHandler as GetMediaAssetQueryHandler;
use Phpresent\Media\Application\Query\GetMediaAssetQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetMediaAssetHandler implements RequestHandlerInterface
{
    public function __construct(private GetMediaAssetQueryHandler $getMediaAssetHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $asset = ($this->getMediaAssetHandler)(new GetMediaAssetQuery($id));

        if ($asset === null) {
            return new JsonResponse(['title' => 'Media asset not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $asset]);
    }
}
