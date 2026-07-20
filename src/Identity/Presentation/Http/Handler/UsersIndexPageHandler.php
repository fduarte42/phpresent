<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Phpresent\Identity\Application\Query\ListRolesHandler;
use Phpresent\Identity\Application\Query\ListRolesQuery;
use Phpresent\Identity\Application\Query\ListUsersHandler;
use Phpresent\Identity\Application\Query\ListUsersQuery;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inertia page handler for Users/Roles management (§18, Admin UI). Unlike
 * every other Index page handler in this codebase, the initial queries
 * here are permission-gated (`users.view`/`roles.view`, §18.2) — an
 * anonymous or under-privileged visitor still gets the page (this app has
 * no route-level auth middleware that rejects requests, §18.4: "It never
 * rejects a request itself"), just with `forbidden: true` and empty lists
 * instead of a fatal error.
 */
final readonly class UsersIndexPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListUsersHandler $listUsersHandler,
        private ListRolesHandler $listRolesHandler,
        private InertiaResponseFactory $inertia,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);
        $actorUserId = is_string($actorUserId) ? $actorUserId : null;

        try {
            $users = ($this->listUsersHandler)(new ListUsersQuery($actorUserId));
            $roles = ($this->listRolesHandler)(new ListRolesQuery($actorUserId));
            $forbidden = false;
        } catch (PermissionDeniedException) {
            $users = [];
            $roles = [];
            $forbidden = true;
        }

        return $this->inertia->render($request, 'Identity/Users', [
            'users' => $users,
            'roles' => $roles,
            'forbidden' => $forbidden,
        ]);
    }
}
