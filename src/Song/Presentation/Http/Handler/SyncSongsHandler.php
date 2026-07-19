<?php

declare(strict_types=1);

namespace Phpresent\Song\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Song\Application\Command\SyncSongsCommand;
use Phpresent\Song\Application\Command\SyncSongsHandler as SyncSongsCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Triggers a foreground SongbookPro sync pass. Intended for admin use;
 * background/scheduled sync is handled separately via Messenger (see
 * docs/sdd.md §6). Authorization is enforced by the auth middleware chain
 * configured on this route, not in this handler.
 */
final readonly class SyncSongsHandler implements RequestHandlerInterface
{
    public function __construct(private SyncSongsCommandHandler $syncSongsHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $forceFullSync = is_array($body) && ($body['forceFullSync'] ?? false) === true;

        $result = ($this->syncSongsHandler)(new SyncSongsCommand($forceFullSync));

        return new JsonResponse(['data' => $result]);
    }
}
