<?php

declare(strict_types=1);

// Non-secret defaults; overridden by config/autoload/local.php (git-ignored)
// and environment variables. See docs/sdd.md §6.
return [
    'songbookpro' => [
        'graphql' => [
            'timeout_seconds' => 10,
            'max_retries' => 3,
            'retry_base_delay_ms' => 200,
            'page_size' => 50,
        ],
    ],
];
