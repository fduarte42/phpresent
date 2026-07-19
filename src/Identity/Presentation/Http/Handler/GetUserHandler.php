<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Identity\Application\Query\GetUserHandler as GetUserQueryHandler;
use Phpresent\Identity\Application\Query\GetUserQuery;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GetUserHandler implements RequestHandlerInterface
{
    public function __construct(private GetUserQueryHandler $getUserHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        try {
            $user = ($this->getUserHandler)(new GetUserQuery(
                actorUserId: is_string($actorUserId) ? $actorUserId : null,
                id: $id,
            ));
        } catch (PermissionDeniedException $exception) {
            return new JsonResponse(['title' => 'Forbidden', 'detail' => $exception->getMessage(), 'status' => 403], 403);
        }

        if ($user === null) {
            return new JsonResponse(['title' => 'User not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $user]);
    }
}
