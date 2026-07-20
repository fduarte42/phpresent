<?php

declare(strict_types=1);

// Non-secret defaults for bin/websocket-server.php (SDD §7.5/§13);
// overridden by config/autoload/local.php and environment variables, same
// pattern as songbookpro.global.php.
return [
    'websocket' => [
        'host' => getenv('WEBSOCKET_HOST') ?: '0.0.0.0',
        'port' => (int) (getenv('WEBSOCKET_PORT') ?: 8090),
        'poll_interval_seconds' => (float) (getenv('WEBSOCKET_POLL_INTERVAL_SECONDS') ?: 0.25),
        // Used by the /sse/{displayId} fallback (same poll technique, over
        // plain HTTP instead of a WebSocket connection). Every poll tick
        // writes at least a ping, both as a heartbeat and so
        // connection_aborted() can actually detect a disconnected client
        // promptly — see PresentationSseHandler's docblock.
        'sse_max_duration_seconds' => (int) (getenv('WEBSOCKET_SSE_MAX_DURATION_SECONDS') ?: 55),
    ],
];
