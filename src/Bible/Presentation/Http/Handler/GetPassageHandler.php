<?php

declare(strict_types=1);

namespace Phpresent\Bible\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Bible\Application\Query\GetBiblePassageHandler;
use Phpresent\Bible\Application\Query\GetBiblePassageQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetPassageHandler implements RequestHandlerInterface
{
    public function __construct(private GetBiblePassageHandler $getBiblePassageHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $passage = ($this->getBiblePassageHandler)(new GetBiblePassageQuery(
            translationId: is_string($params['translationId'] ?? null) ? $params['translationId'] : '',
            book: is_string($params['book'] ?? null) ? $params['book'] : '',
            chapter: is_numeric($params['chapter'] ?? null) ? (int) $params['chapter'] : 0,
            startVerse: is_numeric($params['startVerse'] ?? null) ? (int) $params['startVerse'] : null,
            endVerse: is_numeric($params['endVerse'] ?? null) ? (int) $params['endVerse'] : null,
        ));

        if ($passage === null) {
            return new JsonResponse(['title' => 'Passage not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $passage]);
    }
}
