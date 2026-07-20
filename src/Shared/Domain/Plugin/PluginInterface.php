<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Plugin;

/**
 * Base contract every plugin implements (SDD §12). Narrow capability
 * interfaces (`BibleProviderInterface`, and eventually
 * `MediaProviderInterface`, `ExporterInterface`, `ImporterInterface`,
 * `PresentationWidgetInterface`, `RemoteDeviceInterface`) extend this one
 * rather than replace it, so `PluginRegistry` can hold a single
 * `list<PluginInterface>` and filter by `instanceof` for whichever
 * capability a caller needs.
 */
interface PluginInterface
{
    /**
     * A stable identifier for this plugin, unique across everything
     * registered in `config/plugins.php` — used to attribute results back
     * to their source when multiple plugins implement the same capability.
     */
    public function id(): string;

    public function name(): string;
}
