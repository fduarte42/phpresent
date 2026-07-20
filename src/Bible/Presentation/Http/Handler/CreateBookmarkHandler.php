<?php

declare(strict_types=1);

namespace Phpresent\Bible\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Bible\Application\Command\CreateBookmarkCommand;
use Phpresent\Bible\Application\Command\CreateBookmarkHandler as CreateBookmarkCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CreateBookmarkHandler implements RequestHandlerInterface
{
    public function __construct(private CreateBookmarkCommandHandler $createBookmarkHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $bookmark = ($this->createBookmarkHandler)(new CreateBookmarkCommand(
            translationId: is_string($body['translationId'] ?? null) ? $body['translationId'] : '',
            book: is_string($body['book'] ?? null) ? $body['book'] : '',
            chapter: is_numeric($body['chapter'] ?? null) ? (int) $body['chapter'] : 0,
            startVerse: is_numeric($body['startVerse'] ?? null) ? (int) $body['startVerse'] : null,
            endVerse: is_numeric($body['endVerse'] ?? null) ? (int) $body['endVerse'] : null,
            label: is_string($body['label'] ?? null) ? $body['label'] : null,
        ));

        return new JsonResponse(['data' => $bookmark], 201);
    }
}
