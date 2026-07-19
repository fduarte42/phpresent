<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Identity\Application\Command\DeactivateUserCommand;
use Phpresent\Identity\Application\Command\DeactivateUserHandler as DeactivateUserCommandHandler;
use Phpresent\Identity\Application\DTO\UserDto;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DeactivateUserHandler implements RequestHandlerInterface
{
    public function __construct(private DeactivateUserCommandHandler $deactivateUserHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $userId = (string) $routeResult->getMatchedParams()['id'];

        try {
            $user = ($this->deactivateUserHandler)(new DeactivateUserCommand(
                actorUserId: is_string($actorUserId) ? $actorUserId : null,
                userId: $userId,
            ));
        } catch (PermissionDeniedException $exception) {
            return new JsonResponse(['title' => 'Forbidden', 'detail' => $exception->getMessage(), 'status' => 403], 403);
        }

        if ($user === null) {
            return new JsonResponse(['title' => 'User not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => UserDto::fromEntity($user)]);
    }
}
