<?php

declare(strict_types=1);

namespace Phpresent\Bible\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Bible\Application\Query\ListBibleTranslationsHandler;
use Phpresent\Bible\Application\Query\ListBibleTranslationsQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListTranslationsHandler implements RequestHandlerInterface
{
    public function __construct(private ListBibleTranslationsHandler $listBibleTranslationsHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['data' => ($this->listBibleTranslationsHandler)(new ListBibleTranslationsQuery())]);
    }
}
