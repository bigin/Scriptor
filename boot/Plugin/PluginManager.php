<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

use League\Container\Container;

/**
 * Discovers Scriptor plugins from composer's installed.json and boots
 * them once per request.
 *
 * Discovery rules (matches the plan in `docs/scriptor-plugin-api-plan.md`):
 *
 * - A plugin package declares `"type": "scriptor-plugin"` in its
 *   composer.json.
 * - The same composer.json carries the plugin FQCN under
 *   `extra.scriptor.plugin`.
 * - The FQCN must implement {@see Plugin}.
 *
 * Discovery results land in a PHP cache file (default
 * `data/cache/plugins.php`) so the installed.json scan does not run
 * on every request. Cache freshness is checked by comparing the
 * mtime of the cache file with the mtime of `vendor/composer/installed.json`;
 * any time composer rewrites installed.json (install, update, remove,
 * dump-autoload) the next boot picks up the new state automatically.
 * {@see clearCache()} stays available for callers that want to force
 * a fresh scan explicitly.
 *
 * Disabling a discovered plugin without uninstalling it is possible
 * via `$config['plugins']['disabled']` in scriptor-config.php; the
 * array carries FQCNs that this manager skips at boot.
 */
final class PluginManager
{
    /** @var list<PluginManifest>|null Lazy-populated by {@see discover()}. */
    private ?array $manifests = null;

    /** @var list<Plugin> */
    private array $bootedPlugins = [];

    /**
     * @param list<string> $disabled    FQCNs of plugins to skip at boot.
     * @param list<string> $corePlugins FQCNs of first-party plugins shipped
     *                                  inside Scriptor itself (not via Composer).
     *                                  They boot before discovered plugins, so
     *                                  user plugins can override any service or
     *                                  listener a core plugin registered.
     */
    public function __construct(
        private readonly Container $container,
        private readonly string $vendorDir,
        private readonly string $cachePath,
        private readonly array $disabled = [],
        private readonly array $corePlugins = [],
    ) {}

    /**
     * Returns the discovered plugin manifests, using the cache when
     * available. Pass `forceRefresh: true` to bypass the cache and
     * re-scan installed.json.
     *
     * @return list<PluginManifest>
     */
    public function discover(bool $forceRefresh = false): array
    {
        if ($this->manifests !== null && ! $forceRefresh) {
            return $this->manifests;
        }

        if (! $forceRefresh) {
            $cached = $this->readCache();
            if ($cached !== null) {
                $this->manifests = $cached;
                return $cached;
            }
        }

        $manifests = $this->scanInstalledJson();
        $this->manifests = $manifests;
        $this->writeCache($manifests);
        return $manifests;
    }

    /**
     * Instantiate every plugin (core first, then discovered, skipping
     * disabled ones in both sets), call {@see Plugin::register()}.
     * Idempotent: subsequent calls within the same request are a no-op.
     */
    public function bootAll(): void
    {
        if ($this->bootedPlugins !== []) {
            return;
        }
        $context = new PluginContext($this->container);

        foreach ($this->corePlugins as $class) {
            $this->bootPluginClass($class, $context);
        }
        foreach ($this->discover() as $manifest) {
            $this->bootPluginClass($manifest->pluginClass, $context);
        }
    }

    private function bootPluginClass(string $class, PluginContext $context): void
    {
        if (in_array($class, $this->disabled, true)) {
            return;
        }
        if (! class_exists($class)) {
            return;
        }
        $instance = new $class();
        if (! $instance instanceof Plugin) {
            return;
        }
        $instance->register($context);
        $this->bootedPlugins[] = $instance;
    }

    /** @return list<Plugin> */
    public function bootedPlugins(): array
    {
        return $this->bootedPlugins;
    }

    /**
     * Clear the discovery cache. Composer post-install / post-update
     * scripts should call this so the next boot re-scans installed.json
     * and picks up plugin add/remove.
     */
    public function clearCache(): void
    {
        if (is_file($this->cachePath)) {
            @unlink($this->cachePath);
        }
        $this->manifests = null;
    }

    /**
     * @return list<PluginManifest>
     */
    private function scanInstalledJson(): array
    {
        $installed = $this->vendorDir . '/composer/installed.json';
        if (! is_file($installed)) {
            return [];
        }
        $raw = file_get_contents($installed);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [];
        }

        // Composer 2 wraps packages in {"packages": [...]}, Composer 1
        // shipped a flat list. Support both for forward-compatibility
        // with any tooling that regenerates installed.json oddly.
        $packages = isset($data['packages']) && is_array($data['packages'])
            ? $data['packages']
            : $data;

        $manifests = [];
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }
            if (($package['type'] ?? null) !== 'scriptor-plugin') {
                continue;
            }
            $class = $package['extra']['scriptor']['plugin'] ?? null;
            if (! is_string($class) || $class === '') {
                continue;
            }
            $manifests[] = new PluginManifest(
                packageName: (string) ($package['name'] ?? 'unknown'),
                packageVersion: (string) ($package['version'] ?? '0.0.0'),
                pluginClass: $class,
            );
        }
        return $manifests;
    }

    /**
     * @return list<PluginManifest>|null
     */
    private function readCache(): ?array
    {
        if (! is_file($this->cachePath)) {
            return null;
        }
        // Self-invalidate when composer rewrites installed.json. Any
        // composer install / update / remove / dump-autoload bumps the
        // installed.json mtime, so the cache becomes stale the moment
        // dependencies change. No external hook needed.
        $installed = $this->vendorDir . '/composer/installed.json';
        if (is_file($installed)) {
            $installedMtime = (int) filemtime($installed);
            $cacheMtime     = (int) filemtime($this->cachePath);
            if ($installedMtime > $cacheMtime) {
                return null;
            }
        }
        $cached = @include $this->cachePath;
        if (! is_array($cached)) {
            return null;
        }
        $manifests = [];
        foreach ($cached as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $manifests[] = new PluginManifest(
                packageName:    (string) ($entry['packageName']    ?? 'unknown'),
                packageVersion: (string) ($entry['packageVersion'] ?? '0.0.0'),
                pluginClass:    (string) ($entry['pluginClass']    ?? ''),
            );
        }
        return $manifests;
    }

    /**
     * @param list<PluginManifest> $manifests
     */
    private function writeCache(array $manifests): void
    {
        $dir = dirname($this->cachePath);
        if (! is_dir($dir) && ! @mkdir($dir, 0o775, true) && ! is_dir($dir)) {
            return;
        }
        $payload = array_map(
            static fn (PluginManifest $m): array => [
                'packageName'    => $m->packageName,
                'packageVersion' => $m->packageVersion,
                'pluginClass'    => $m->pluginClass,
            ],
            $manifests,
        );
        $php = "<?php\n\n// Generated by Scriptor\\Boot\\Plugin\\PluginManager.\n"
             . "// Delete this file or call PluginManager::clearCache() after\n"
             . "// composer install/update so new plugins are picked up.\n\n"
             . 'return ' . var_export($payload, true) . ";\n";
        @file_put_contents($this->cachePath, $php);
    }
}
