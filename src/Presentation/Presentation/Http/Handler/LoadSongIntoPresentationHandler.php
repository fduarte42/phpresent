<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Presentation\Application\Command\LoadSongIntoPresentationCommand;
use Phpresent\Presentation\Application\Command\LoadSongIntoPresentationHandler as LoadSongCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class LoadSongIntoPresentationHandler implements RequestHandlerInterface
{
    public function __construct(private LoadSongCommandHandler $loadSongIntoPresentationHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $songId = is_array($body) && is_string($body['songId'] ?? null) ? $body['songId'] : '';

        $session = ($this->loadSongIntoPresentationHandler)(new LoadSongIntoPresentationCommand($songId));

        if ($session === null) {
            return new JsonResponse(['title' => 'Song not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $session]);
    }
}
