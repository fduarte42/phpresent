<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Phpresent\Identity\Application\Command\LoginCommand;
use Phpresent\Identity\Application\Command\LoginHandler as LoginCommandHandler;
use Phpresent\Identity\Domain\Exception\InvalidCredentialsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class LoginHandler implements RequestHandlerInterface
{
    public function __construct(private LoginCommandHandler $loginHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $email = is_array($body) && is_string($body['email'] ?? null) ? $body['email'] : '';
        $password = is_array($body) && is_string($body['password'] ?? null) ? $body['password'] : '';

        try {
            $user = ($this->loginHandler)(new LoginCommand($email, $password));
        } catch (InvalidCredentialsException $exception) {
            return new JsonResponse(['title' => $exception->getMessage(), 'status' => 401], 401);
        }

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if ($session instanceof SessionInterface) {
            $session->set('userId', $user->id);
        }

        return new JsonResponse(['data' => $user]);
    }
}
