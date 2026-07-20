<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Presentation\Application\Command\CreateDisplayCommand;
use Phpresent\Presentation\Application\Command\CreateDisplayHandler as CreateDisplayCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ValueError;

final readonly class CreateDisplayHandler implements RequestHandlerInterface
{
    public function __construct(private CreateDisplayCommandHandler $createDisplayHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        try {
            $display = ($this->createDisplayHandler)(new CreateDisplayCommand(
                name: is_string($body['name'] ?? null) ? $body['name'] : '',
                role: is_string($body['role'] ?? null) ? $body['role'] : '',
                settings: is_array($body['settings'] ?? null) ? $body['settings'] : [],
            ));
        } catch (ValueError $exception) {
            return new JsonResponse(['title' => 'Invalid display role', 'status' => 400], 400);
        }

        return new JsonResponse(['data' => $display], 201);
    }
}
