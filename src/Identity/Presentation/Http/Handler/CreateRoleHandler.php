<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Identity\Application\Command\CreateRoleCommand;
use Phpresent\Identity\Application\Command\CreateRoleHandler as CreateRoleCommandHandler;
use Phpresent\Identity\Application\DTO\RoleDto;
use Phpresent\Identity\Domain\Exception\DuplicateRoleNameException;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CreateRoleHandler implements RequestHandlerInterface
{
    public function __construct(private CreateRoleCommandHandler $createRoleHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        /** @var list<string> $permissions */
        $permissions = is_array($body['permissions'] ?? null)
            ? array_map(static fn (mixed $permission): string => (string) $permission, $body['permissions'])
            : [];

        try {
            $role = ($this->createRoleHandler)(new CreateRoleCommand(
                actorUserId: is_string($actorUserId) ? $actorUserId : null,
                name: is_string($body['name'] ?? null) ? $body['name'] : '',
                permissions: $permissions,
            ));
        } catch (PermissionDeniedException $exception) {
            return new JsonResponse(['title' => 'Forbidden', 'detail' => $exception->getMessage(), 'status' => 403], 403);
        } catch (DuplicateRoleNameException $exception) {
            return new JsonResponse(['title' => 'Invalid request', 'detail' => $exception->getMessage(), 'status' => 422], 422);
        }

        return new JsonResponse(['data' => RoleDto::fromEntity($role)], 201);
    }
}
