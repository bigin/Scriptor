<?php

declare(strict_types=1);

namespace Scriptor\Boot\Cli;

use League\Container\Container;
use Scriptor\Boot\App;
use Scriptor\Boot\ImanagerBootstrap;
use Scriptor\Boot\Plugin\LifecyclePlugin;
use Scriptor\Boot\Plugin\PluginContext;
use Scriptor\Boot\Plugin\PluginManager;
use Scriptor\Boot\Plugin\PluginStateManager;

/**
 * `bin/scriptor plugin:install <package>` implementation.
 *
 * Bootstraps the iManager container, finds the named composer package
 * in the discovery cache, instantiates its plugin class, and invokes
 * `LifecyclePlugin::install()` exactly once. On success the package
 * is recorded in `data/plugin-states.json`.
 *
 * Safety rules:
 *   - Refuses if the package is not discovered (typo / not composer-required).
 *   - Refuses if the plugin class does not implement LifecyclePlugin —
 *     stateless plugins have no install hook to call.
 *   - Refuses if the package is already marked installed in state.json,
 *     unless `--force` is passed (operator deliberately re-running setup).
 *   - Errors in install() are NOT auto-rolled-back; the state file
 *     stays unmodified so the operator can fix and retry without a
 *     stale "installed" marker.
 *
 * `--all` installs every discovered lifecycle plugin that isn't
 * already installed. Useful right after `composer install` on a fresh
 * deployment.
 */
final class PluginInstallCommand
{
    private const EXIT_OK              = 0;
    private const EXIT_NOT_FOUND       = 1;
    private const EXIT_NOT_LIFECYCLE   = 2;
    private const EXIT_ALREADY         = 3;
    private const EXIT_UNEXPECTED      = 4;

    public function __construct(
        private readonly string $scriptorRoot,
        private readonly Console $console,
    ) {}

    /**
     * @param array<string, mixed> $options
     * @param list<string>         $positional
     */
    public function run(array $options, array $positional): int
    {
        try {
            return $this->runUnsafe($options, $positional);
        } catch (\Throwable $e) {
            $this->console->errln('plugin:install failed: ' . $e->getMessage());
            $this->console->errln('  in ' . $e->getFile() . ':' . $e->getLine());
            return self::EXIT_UNEXPECTED;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string>         $positional
     */
    private function runUnsafe(array $options, array $positional): int
    {
        $force = (bool) ($options['force'] ?? false);
        $all   = (bool) ($options['all']   ?? false);

        $manager = new PluginManager(
            container:   new Container(),
            vendorDir:   $this->scriptorRoot . '/vendor',
            cachePath:   $this->scriptorRoot . '/data/cache/plugins.php',
            disabled:    [],
            corePlugins: [],
        );
        $manifests = $manager->discover(forceRefresh: true);
        $state     = new PluginStateManager($this->scriptorRoot);

        // Build the working list of packages to install.
        if ($all) {
            $targets = [];
            foreach ($manifests as $m) {
                if (! \class_exists($m->pluginClass)) continue;
                if (! \is_subclass_of($m->pluginClass, LifecyclePlugin::class)) continue;
                if ($state->isInstalled($m->packageName) && ! $force) continue;
                $targets[] = $m;
            }
            if ($targets === []) {
                $this->console->writeln('No pending lifecycle plugins to install.');
                return self::EXIT_OK;
            }
        } else {
            if (\count($positional) !== 1) {
                $this->console->errln('Expected exactly one <package> argument, or pass --all.');
                return self::EXIT_NOT_FOUND;
            }
            $package = $positional[0];
            $hit = null;
            foreach ($manifests as $m) {
                if ($m->packageName === $package) {
                    $hit = $m;
                    break;
                }
            }
            if ($hit === null) {
                $this->console->errln("Package not discovered: {$package}");
                $this->console->errln('  (run `composer require ' . $package . '` first)');
                return self::EXIT_NOT_FOUND;
            }
            if (! \class_exists($hit->pluginClass)) {
                $this->console->errln("Plugin class missing: {$hit->pluginClass}");
                return self::EXIT_NOT_FOUND;
            }
            if (! \is_subclass_of($hit->pluginClass, LifecyclePlugin::class)) {
                $this->console->errln("Package {$package} is a stateless Plugin (no install hook).");
                $this->console->errln('  It is loaded automatically per request — nothing to install.');
                return self::EXIT_NOT_LIFECYCLE;
            }
            if ($state->isInstalled($package) && ! $force) {
                $this->console->errln("Package {$package} is already installed.");
                $this->console->errln('  Pass --force to re-run install().');
                return self::EXIT_ALREADY;
            }
            $targets = [$hit];
        }

        // Bootstrap iManager once for the whole batch.
        $container = ImanagerBootstrap::create($this->scriptorRoot);
        App::set($container);

        $installed = 0;
        foreach ($targets as $manifest) {
            $this->console->writeln('Installing ' . $manifest->packageName . ' (' . $manifest->packageVersion . ')...');
            /** @var LifecyclePlugin $instance */
            $instance = new $manifest->pluginClass();
            $context  = new PluginContext($container, $instance->name());
            $instance->install($context);
            $state->markInstalled($manifest->packageName, $manifest->packageVersion);
            $installed++;
            $this->console->writeln('  ok.');
        }

        $this->console->writeln('');
        $this->console->writeln(\sprintf('%d plugin(s) installed.', $installed));
        return self::EXIT_OK;
    }
}
