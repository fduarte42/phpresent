<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Presentation\Application\Query\GetPresentationSessionHandler as GetPresentationSessionQueryHandler;
use Phpresent\Presentation\Application\Query\GetPresentationSessionQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetPresentationSessionHandler implements RequestHandlerInterface
{
    public function __construct(private GetPresentationSessionQueryHandler $getPresentationSessionHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['data' => ($this->getPresentationSessionHandler)(new GetPresentationSessionQuery())]);
    }
}
