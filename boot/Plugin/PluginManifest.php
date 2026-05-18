<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

/**
 * A single entry from composer's installed.json that names a Scriptor
 * plugin. The {@see PluginManager} stores these in its discovery
 * cache so the JSON scan only runs once per cache-bust.
 */
final readonly class PluginManifest
{
    public function __construct(
        public string $packageName,
        public string $packageVersion,
        public string $pluginClass,
    ) {}
}
