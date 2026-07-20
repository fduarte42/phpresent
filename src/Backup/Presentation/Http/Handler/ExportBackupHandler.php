<?php

declare(strict_types=1);

namespace Phpresent\Backup\Presentation\Http\Handler;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Backup\Application\Command\ExportBackupCommand;
use Phpresent\Backup\Application\Command\ExportBackupHandler as ExportBackupCommandHandler;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ExportBackupHandler implements RequestHandlerInterface
{
    public function __construct(private ExportBackupCommandHandler $exportBackupHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);

        try {
            $archive = ($this->exportBackupHandler)(new ExportBackupCommand(
                is_string($actorUserId) ? $actorUserId : null,
            ));
        } catch (PermissionDeniedException $exception) {
            return new JsonResponse(['title' => 'Forbidden', 'detail' => $exception->getMessage(), 'status' => 403], 403);
        }

        $filename = 'phpresent-backup-' . date('Y-m-d-His') . '.zip';

        return new Response($archive, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
