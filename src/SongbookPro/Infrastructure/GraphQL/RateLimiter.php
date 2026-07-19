<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

use Closure;

/**
 * Simple in-process token bucket. One instance is shared (via the DI
 * container) across all requests made by this process, so bursts of
 * paginated GraphQL calls stay under the configured rate.
 */
final class RateLimiter
{
    private float $tokens;
    private float $lastRefillAt;

    public function __construct(
        private readonly float $ratePerSecond,
        private readonly ?Closure $sleep = null,
    ) {
        $this->tokens = $ratePerSecond;
        $this->lastRefillAt = microtime(true);
    }

    public function acquire(): void
    {
        $this->refill();

        if ($this->tokens < 1.0) {
            $wait = (1.0 - $this->tokens) / $this->ratePerSecond;
            $this->doSleep($wait);
            $this->refill();
        }

        $this->tokens -= 1.0;
    }

    private function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefillAt;
        $this->tokens = min($this->ratePerSecond, $this->tokens + $elapsed * $this->ratePerSecond);
        $this->lastRefillAt = $now;
    }

    private function doSleep(float $seconds): void
    {
        if ($this->sleep !== null) {
            ($this->sleep)($seconds);

            return;
        }

        usleep((int) ($seconds * 1_000_000));
    }
}
