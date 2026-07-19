<?php

declare(strict_types=1);

namespace Phpresent\SongSet\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\SongSet\Application\Command\SyncSongSetsCommand;
use Phpresent\SongSet\Application\Command\SyncSongSetsHandler as SyncSongSetsCommandHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Triggers a foreground SongbookPro sync pass. Intended for admin use;
 * background/scheduled sync is handled separately via Messenger (see
 * docs/sdd.md §6). Authorization is enforced by the auth middleware chain
 * configured on this route, not in this handler.
 */
final readonly class SyncSongSetsHandler implements RequestHandlerInterface
{
    public function __construct(private SyncSongSetsCommandHandler $syncSongSetsHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $forceFullSync = is_array($body) && ($body['forceFullSync'] ?? false) === true;

        $result = ($this->syncSongSetsHandler)(new SyncSongSetsCommand($forceFullSync));

        return new JsonResponse(['data' => $result]);
    }
}
