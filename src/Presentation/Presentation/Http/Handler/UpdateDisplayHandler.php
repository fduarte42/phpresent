<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Presentation\Application\Command\UpdateDisplayCommand;
use Phpresent\Presentation\Application\Command\UpdateDisplayHandler as UpdateDisplayCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ValueError;

final readonly class UpdateDisplayHandler implements RequestHandlerInterface
{
    public function __construct(private UpdateDisplayCommandHandler $updateDisplayHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        try {
            $display = ($this->updateDisplayHandler)(new UpdateDisplayCommand(
                id: $id,
                name: is_string($body['name'] ?? null) ? $body['name'] : '',
                role: is_string($body['role'] ?? null) ? $body['role'] : '',
                settings: is_array($body['settings'] ?? null) ? $body['settings'] : [],
            ));
        } catch (ValueError $exception) {
            return new JsonResponse(['title' => 'Invalid display role', 'status' => 400], 400);
        }

        if ($display === null) {
            return new JsonResponse(['title' => 'Display not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $display]);
    }
}
