<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Presentation\Application\Query\ListDisplaysHandler as ListDisplaysQueryHandler;
use Phpresent\Presentation\Application\Query\ListDisplaysQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListDisplaysHandler implements RequestHandlerInterface
{
    public function __construct(private ListDisplaysQueryHandler $listDisplaysHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['data' => ($this->listDisplaysHandler)(new ListDisplaysQuery())]);
    }
}
