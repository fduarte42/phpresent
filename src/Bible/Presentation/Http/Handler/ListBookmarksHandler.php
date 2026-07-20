<?php

declare(strict_types=1);

namespace Phpresent\Bible\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Bible\Application\Query\ListBookmarksHandler as ListBookmarksQueryHandler;
use Phpresent\Bible\Application\Query\ListBookmarksQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListBookmarksHandler implements RequestHandlerInterface
{
    public function __construct(private ListBookmarksQueryHandler $listBookmarksHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['data' => ($this->listBookmarksHandler)(new ListBookmarksQuery())]);
    }
}
