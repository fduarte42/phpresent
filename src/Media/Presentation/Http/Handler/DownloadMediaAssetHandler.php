<?php

declare(strict_types=1);

namespace Phpresent\Media\Presentation\Http\Handler;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Media\Application\Query\GetMediaAssetContentHandler;
use Phpresent\Media\Application\Query\GetMediaAssetContentQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DownloadMediaAssetHandler implements RequestHandlerInterface
{
    public function __construct(private GetMediaAssetContentHandler $getMediaAssetContentHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $content = ($this->getMediaAssetContentHandler)(new GetMediaAssetContentQuery($id));

        if ($content === null) {
            return new JsonResponse(['title' => 'Media asset not found', 'status' => 404], 404);
        }

        return new Response($content->stream, 200, [
            'Content-Type' => $content->mimeType,
            'Content-Disposition' => sprintf('inline; filename="%s"', addslashes($content->filename)),
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
