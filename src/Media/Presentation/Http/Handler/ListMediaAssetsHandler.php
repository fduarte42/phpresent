<?php

declare(strict_types=1);

namespace Phpresent\Media\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Media\Application\Query\SearchMediaAssetsHandler;
use Phpresent\Media\Application\Query\SearchMediaAssetsQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListMediaAssetsHandler implements RequestHandlerInterface
{
    public function __construct(private SearchMediaAssetsHandler $searchMediaAssetsHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $query = new SearchMediaAssetsQuery(
            query: is_string($params['q'] ?? null) ? $params['q'] : '',
            limit: is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 50,
            offset: is_numeric($params['offset'] ?? null) ? (int) $params['offset'] : 0,
        );

        return new JsonResponse(['data' => ($this->searchMediaAssetsHandler)($query)]);
    }
}
