<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Phpresent\Presentation\Application\Query\GetPresentationSessionHandler;
use Phpresent\Presentation\Application\Query\GetPresentationSessionQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SSE fallback for environments where WebSocket is blocked (SDD §7.5/§13),
 * mirroring `PresentationChannel`'s poll-and-diff technique so both
 * transports carry identical semantics — see that class's docblock for why
 * polling, not a message broker, is the mechanism.
 *
 * `{displayId}` is accepted (matching §13's `/sse/{displayId}` path) but
 * unused today — the session broadcast is the same regardless of which
 * display is watching, since there's no per-display content variation yet.
 *
 * Two caveats confirmed by actually booting this and testing it end to end
 * (SDD §16.7's whole point — code that "should just work" often doesn't):
 *
 * - `connection_aborted()` is only accurate immediately after PHP attempts
 *   to write output — it does *not* update passively in the background.
 *   An earlier version checked it before a tick that might not write
 *   anything (only sending on change, heartbeating every 15s), so a client
 *   that disconnected during a quiet period went undetected for up to 15s
 *   — under `composer serve`'s single-worker dev server, that meant *every
 *   other request blocked for up to 15s* after any SSE client vanished.
 *   Fixed by writing a minimal `: ping` on every tick (not just on
 *   heartbeat) and checking `connection_aborted()` right after — disconnect
 *   detection latency is now one `pollIntervalSeconds`, not fifteen.
 * - Relies on PHP echoing + flushing incrementally while `getContents()`
 *   runs, which depends on the runtime's output-buffering configuration —
 *   confirmed working under `composer serve`; verify it again behind
 *   whatever production runtime (php-fpm, RoadRunner, ...) is chosen,
 *   since that's a different SAPI with its own buffering behavior.
 */
final readonly class PresentationSseHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetPresentationSessionHandler $getPresentationSessionHandler,
        private EntityManagerInterface $entityManager,
        private float $pollIntervalSeconds = 0.25,
        private int $maxDurationSeconds = 55,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $stream = new CallbackStream(function (): string {
            $this->stream();

            return '';
        });

        return new Response($stream, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function stream(): void
    {
        $lastPayload = null;
        $deadline = microtime(true) + $this->maxDurationSeconds;

        while (microtime(true) < $deadline) {
            $payload = $this->currentPayload();
            $changed = $payload !== $lastPayload;

            echo $changed ? "data: {$payload}\n\n" : ": ping\n\n";
            $this->flushOutput();
            $lastPayload = $payload;

            if (connection_aborted() !== 0) {
                return;
            }

            usleep((int) ($this->pollIntervalSeconds * 1_000_000));
        }
    }

    private function currentPayload(): string
    {
        $this->entityManager->clear();
        $dto = ($this->getPresentationSessionHandler)(new GetPresentationSessionQuery());

        return json_encode(['data' => $dto], JSON_THROW_ON_ERROR);
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
