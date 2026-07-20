<?php

declare(strict_types=1);

namespace Phpresent\Backup\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Backup\Application\Command\ImportBackupCommand;
use Phpresent\Backup\Application\Command\ImportBackupHandler as ImportBackupCommandHandler;
use Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use const UPLOAD_ERR_OK;

final readonly class ImportBackupHandler implements RequestHandlerInterface
{
    public function __construct(private ImportBackupCommandHandler $importBackupHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorUserId = $request->getAttribute(AuthenticationMiddleware::ACTOR_ATTRIBUTE);

        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return new JsonResponse(['title' => 'No archive uploaded (expected field "file")', 'status' => 400], 400);
        }

        try {
            $result = ($this->importBackupHandler)(new ImportBackupCommand(
                is_string($actorUserId) ? $actorUserId : null,
                $file->getStream(),
            ));
        } catch (PermissionDeniedException $exception) {
            return new JsonResponse(['title' => 'Forbidden', 'detail' => $exception->getMessage(), 'status' => 403], 403);
        } catch (RuntimeException $exception) {
            return new JsonResponse(['title' => 'Invalid archive', 'detail' => $exception->getMessage(), 'status' => 422], 422);
        }

        return new JsonResponse(['data' => $result]);
    }
}
