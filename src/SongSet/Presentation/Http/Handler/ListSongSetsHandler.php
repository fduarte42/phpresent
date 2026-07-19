<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\SongSet\Application\Query\SearchSongSetsHandler;
use Phpresent\SongSet\Application\Query\SearchSongSetsQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListSongSetsHandler implements RequestHandlerInterface
{
    public function __construct(private SearchSongSetsHandler $searchSongSetsHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $query = new SearchSongSetsQuery(
            query: is_string($params['q'] ?? null) ? $params['q'] : '',
            limit: is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 50,
            offset: is_numeric($params['offset'] ?? null) ? (int) $params['offset'] : 0,
        );

        $songSets = ($this->searchSongSetsHandler)($query);

        return new JsonResponse(['data' => $songSets]);
    }
}
