<?php

declare(strict_types=1);

namespace Phpresent\Theme\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Theme\Application\Query\ListThemesHandler as ListThemesQueryHandler;
use Phpresent\Theme\Application\Query\ListThemesQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListThemesHandler implements RequestHandlerInterface
{
    public function __construct(private ListThemesQueryHandler $listThemesHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['data' => ($this->listThemesHandler)(new ListThemesQuery())]);
    }
}
