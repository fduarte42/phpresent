<?php

declare(strict_types=1);

namespace Phpresent\Song\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Song\Application\Query\SearchSongsHandler;
use Phpresent\Song\Application\Query\SearchSongsQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListSongsHandler implements RequestHandlerInterface
{
    public function __construct(private SearchSongsHandler $searchSongsHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $query = new SearchSongsQuery(
            query: is_string($params['q'] ?? null) ? $params['q'] : '',
            limit: is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 50,
            offset: is_numeric($params['offset'] ?? null) ? (int) $params['offset'] : 0,
        );

        $songs = ($this->searchSongsHandler)($query);

        return new JsonResponse(['data' => $songs]);
    }
}
