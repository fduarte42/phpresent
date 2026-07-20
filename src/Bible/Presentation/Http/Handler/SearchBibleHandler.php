<?php

declare(strict_types=1);

namespace Phpresent\Bible\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Bible\Application\Query\SearchBibleHandler as SearchBibleQueryHandler;
use Phpresent\Bible\Application\Query\SearchBibleQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class SearchBibleHandler implements RequestHandlerInterface
{
    public function __construct(private SearchBibleQueryHandler $searchBibleHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $query = new SearchBibleQuery(
            translationId: is_string($params['translationId'] ?? null) ? $params['translationId'] : '',
            query: is_string($params['q'] ?? null) ? $params['q'] : '',
            limit: is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 20,
        );

        return new JsonResponse(['data' => ($this->searchBibleHandler)($query)]);
    }
}
