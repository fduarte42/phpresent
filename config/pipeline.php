<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->pipe(Mezzio\Helper\ServerUrlMiddleware::class);
    $app->pipe(Mezzio\Router\Middleware\RouteMiddleware::class);
    $app->pipe(Mezzio\Router\Middleware\ImplicitHeadMiddleware::class);
    $app->pipe(Mezzio\Router\Middleware\ImplicitOptionsMiddleware::class);
    $app->pipe(Mezzio\Router\Middleware\MethodNotAllowedMiddleware::class);
    $app->pipe(Mezzio\Helper\UrlHelperMiddleware::class);
    $app->pipe(Mezzio\ProblemDetails\ProblemDetailsMiddleware::class);
    $app->pipe(Mezzio\Helper\BodyParams\BodyParamsMiddleware::class);
    $app->pipe(Mezzio\Session\SessionMiddleware::class);
    $app->pipe(Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware::class);
    $app->pipe(Mezzio\Router\Middleware\DispatchMiddleware::class);
    $app->pipe(Mezzio\Handler\NotFoundHandler::class);
};
