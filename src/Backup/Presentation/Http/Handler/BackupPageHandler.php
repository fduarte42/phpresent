<?php

declare(strict_types=1);

namespace Phpresent\Backup\Presentation\Http\Handler;

use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class BackupPageHandler implements RequestHandlerInterface
{
    public function __construct(private InertiaResponseFactory $inertia)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inertia->render($request, 'Backup/Index');
    }
}
