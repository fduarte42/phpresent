<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Identity\Application\Command\CreateUserCommand;
use Phpresent\Identity\Application\Command\CreateUserHandler as CreateUserCommandHandler;
use Phpresent\Identity\Application\DTO\UserDto;
use Phpresent\Identity\Domain\Exception\DuplicateEmailException;
use Phpresent\Identity\Domain\Exception\InvalidEmailException;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CreateUserHandler implements RequestHandlerInterface
{
    public function __construct(private CreateUserCommandHandler $createUserHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        /** @var list<string> $roleIds */
        $roleIds = is_array($body['roleIds'] ?? null)
            ? array_map(static fn (mixed $id): string => (string) $id, $body['roleIds'])
            : [];

        try {
            $user = ($this->createUserHandler)(new CreateUserCommand(
                actorUserId: is_string($actorUserId) ? $actorUserId : null,
                email: is_string($body['email'] ?? null) ? $body['email'] : '',
                password: is_string($body['password'] ?? null) ? $body['password'] : '',
                displayName: is_string($body['displayName'] ?? null) ? $body['displayName'] : '',
                roleIds: $roleIds,
            ));
        } catch (PermissionDeniedException $exception) {
            return new JsonResponse(['title' => 'Forbidden', 'detail' => $exception->getMessage(), 'status' => 403], 403);
        } catch (InvalidEmailException|DuplicateEmailException $exception) {
            return new JsonResponse(['title' => 'Invalid request', 'detail' => $exception->getMessage(), 'status' => 422], 422);
        }

        return new JsonResponse(['data' => UserDto::fromEntity($user)], 201);
    }
}
