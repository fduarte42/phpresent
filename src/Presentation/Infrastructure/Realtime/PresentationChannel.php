<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Infrastructure\Realtime;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Presentation\Application\Query\GetPresentationSessionHandler;
use Phpresent\Presentation\Application\Query\GetPresentationSessionQuery;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;
use Throwable;

/**
 * Ratchet component for `bin/websocket-server.php` (SDD §7.5/§13).
 *
 * There is no Messenger transport wired in this codebase yet (confirmed
 * across every prior increment — see SDD §16.4/§16.8a's "no Messenger"
 * notes), and the WebSocket server is a separate OS process from the
 * Mezzio HTTP app that handles `/api/presentation/*` commands, so there is
 * no in-process event to subscribe to. Rather than stand up a real message
 * broker (Redis pub/sub, AMQP) for a single global row, this polls
 * `presentation_sessions` on a timer via the same Doctrine EntityManager
 * config the HTTP app uses (§13: "sharing the Doctrine EntityManager
 * config") and only broadcasts when the serialized state actually changes.
 * `EntityManager::clear()` before every poll is required — without it,
 * Doctrine's identity map would keep returning the first-loaded (now
 * stale) entity instead of reading the row the HTTP process just wrote.
 */
final class PresentationChannel implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, null> */
    private SplObjectStorage $connections;

    private ?string $lastPayload = null;

    public function __construct(
        private readonly GetPresentationSessionHandler $getPresentationSessionHandler,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->connections = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->connections[$conn] = null;
        $conn->send($this->currentPayload());
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // No client-to-server protocol yet — state is push-only.
    }

    public function onClose(ConnectionInterface $conn): void
    {
        unset($this->connections[$conn]);
    }

    public function onError(ConnectionInterface $conn, Throwable $e): void
    {
        $conn->close();
    }

    /**
     * Called on a timer by `bin/websocket-server.php`. Broadcasts only when
     * the session actually changed since the last poll, so idle displays
     * aren't sent an identical frame every tick.
     */
    public function poll(): void
    {
        $payload = $this->currentPayload();

        if ($payload === $this->lastPayload) {
            return;
        }

        $this->lastPayload = $payload;

        foreach ($this->connections as $connection) {
            $connection->send($payload);
        }
    }

    private function currentPayload(): string
    {
        $this->entityManager->clear();
        $dto = ($this->getPresentationSessionHandler)(new GetPresentationSessionQuery());

        return json_encode(['data' => $dto], JSON_THROW_ON_ERROR);
    }
}
