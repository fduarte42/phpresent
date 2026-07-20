<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Plugin;

use Phpresent\Shared\Domain\Plugin\Bible\BibleProviderInterface;
use Phpresent\Shared\Domain\Plugin\PluginInterface;

/**
 * Holds every plugin registered in `config/plugins.php` (SDD §12 —
 * explicit registration, no filesystem scanning/magic) and filters them by
 * capability. New capability interfaces (`MediaProviderInterface`,
 * `ExporterInterface`, ...) get their own typed accessor here as they're
 * added, mirroring `bibleProviders()` — this class deliberately doesn't
 * expose a generic `byCapability(string $interface)` method, since a typed
 * accessor per capability is what lets callers avoid an `instanceof`
 * check/cast at every call site.
 */
final class PluginRegistry
{
    /**
     * @param list<PluginInterface> $plugins
     */
    public function __construct(private readonly array $plugins)
    {
    }

    /**
     * @return list<BibleProviderInterface>
     */
    public function bibleProviders(): array
    {
        return array_values(array_filter(
            $this->plugins,
            static fn (PluginInterface $plugin): bool => $plugin instanceof BibleProviderInterface,
        ));
    }
}
