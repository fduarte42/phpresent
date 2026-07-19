<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Identity\Application\Query\ListUsersHandler as ListUsersQueryHandler;
use Phpresent\Identity\Application\Query\ListUsersQuery;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ListUsersHandler implements RequestHandlerInterface
{
    public function __construct(private ListUsersQueryHandler $listUsersHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);
        $params = $request->getQueryParams();

        try {
            $users = ($this->listUsersHandler)(new ListUsersQuery(
                actorUserId: is_string($actorUserId) ? $actorUserId : null,
                limit: is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 50,
                offset: is_numeric($params['offset'] ?? null) ? (int) $params['offset'] : 0,
            ));
        } catch (PermissionDeniedException $exception) {
            return new JsonResponse(['title' => 'Forbidden', 'detail' => $exception->getMessage(), 'status' => 403], 403);
        }

        return new JsonResponse(['data' => $users]);
    }
}
