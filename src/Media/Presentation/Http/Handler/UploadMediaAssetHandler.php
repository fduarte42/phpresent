<?php

declare(strict_types=1);

namespace Phpresent\Media\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Media\Application\Command\UploadMediaAssetCommand;
use Phpresent\Media\Application\Command\UploadMediaAssetHandler as UploadMediaAssetCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

use const UPLOAD_ERR_OK;

final readonly class UploadMediaAssetHandler implements RequestHandlerInterface
{
    public function __construct(
        private UploadMediaAssetCommandHandler $uploadMediaAssetHandler,
        private int $maxUploadBytes,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file instanceof UploadedFileInterface) {
            return new JsonResponse(['title' => 'No file uploaded (expected field "file")', 'status' => 400], 400);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return new JsonResponse(['title' => 'Upload failed', 'status' => 400], 400);
        }

        $size = $file->getSize() ?? 0;

        if ($size > $this->maxUploadBytes) {
            return new JsonResponse([
                'title' => sprintf('File exceeds the %d byte upload limit', $this->maxUploadBytes),
                'status' => 413,
            ], 413);
        }

        $asset = ($this->uploadMediaAssetHandler)(new UploadMediaAssetCommand(
            filename: $file->getClientFilename() ?? 'upload',
            mimeType: $file->getClientMediaType() ?? 'application/octet-stream',
            sizeBytes: $size,
            contents: $file->getStream(),
        ));

        return new JsonResponse(['data' => $asset], 201);
    }
}
