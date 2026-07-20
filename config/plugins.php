<?php

declare(strict_types=1);

// Explicit plugin registration (SDD §12) — no filesystem scanning/magic.
// Each class must implement Phpresent\Shared\Domain\Plugin\PluginInterface
// (directly or via a capability interface like BibleProviderInterface) and
// be resolvable through the DI container like anything else.
return [
    'plugins' => [
        \Phpresent\Bible\Infrastructure\Plugin\LocalBibleProvider::class,
    ],
];
