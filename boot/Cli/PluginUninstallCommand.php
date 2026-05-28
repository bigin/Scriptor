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
 * `bin/scriptor plugin:uninstall <package>` implementation.
 *
 * Invokes `LifecyclePlugin::uninstall()` on the discovered plugin
 * class for `<package>` and removes the package from the lifecycle
 * state file. The composer package itself stays in vendor/ — this
 * command does NOT run `composer remove`; that's the operator's
 * next step (after which `plugin:list` should show no leftover
 * entries).
 *
 * Convention: the plugin's `uninstall()` body removes its schema
 * (field definitions, custom categories) but LEAVES row values in
 * `items.data` alone, so a later reinstall + plugin:install picks
 * the data back up. Operators who want a hard wipe pass
 * `--purge-data`; the flag is forwarded via `PluginContext::$purgeDataRequested`,
 * which the plugin checks inside its uninstall() body.
 *
 * Errors in uninstall() do NOT remove the state entry — the operator
 * fixes the failure and retries. `--force-state-clear` exists as an
 * escape hatch when the plugin's uninstall() is broken and the
 * operator just wants the state file cleaned up.
 */
final class PluginUninstallCommand
{
    private const EXIT_OK              = 0;
    private const EXIT_NOT_FOUND       = 1;
    private const EXIT_NOT_LIFECYCLE   = 2;
    private const EXIT_NOT_INSTALLED   = 3;
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
            $this->console->errln('plugin:uninstall failed: ' . $e->getMessage());
            $this->console->errln('  in ' . $e->getFile() . ':' . $e->getLine());
            $this->console->errln('  state entry not removed; fix and retry, or use --force-state-clear');
            return self::EXIT_UNEXPECTED;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string>         $positional
     */
    private function runUnsafe(array $options, array $positional): int
    {
        if (\count($positional) !== 1) {
            $this->console->errln('Expected exactly one <package> argument.');
            return self::EXIT_NOT_FOUND;
        }
        $package         = $positional[0];
        $purgeData       = (bool) ($options['purge-data']        ?? false);
        $forceStateClear = (bool) ($options['force-state-clear'] ?? false);

        $state = new PluginStateManager($this->scriptorRoot);
        if (! $state->isInstalled($package)) {
            $this->console->errln("Package {$package} is not marked installed (check `plugin:list`).");
            return self::EXIT_NOT_INSTALLED;
        }

        $manager = new PluginManager(
            container:   new Container(),
            vendorDir:   $this->scriptorRoot . '/vendor',
            cachePath:   $this->scriptorRoot . '/data/cache/plugins.php',
            disabled:    [],
            corePlugins: [],
        );
        $manifests = $manager->discover(forceRefresh: true);
        $hit = null;
        foreach ($manifests as $m) {
            if ($m->packageName === $package) {
                $hit = $m;
                break;
            }
        }

        // Orphan path: state says installed but package is gone. The
        // proper command for that is `plugin:cleanup-orphan`; the
        // uninstall command refuses unless --force-state-clear is set
        // (in which case we just drop the state entry).
        if ($hit === null) {
            if ($forceStateClear) {
                $state->unmark($package);
                $this->console->writeln("Cleared orphan state entry for {$package}.");
                return self::EXIT_OK;
            }
            $this->console->errln("Package {$package} is in state.json but not discovered (orphan).");
            $this->console->errln('  Run `bin/scriptor plugin:cleanup-orphan ' . $package . '` instead.');
            return self::EXIT_NOT_FOUND;
        }

        if (! \class_exists($hit->pluginClass)) {
            if ($forceStateClear) {
                $state->unmark($package);
                $this->console->writeln("Cleared state entry for {$package} (class missing).");
                return self::EXIT_OK;
            }
            $this->console->errln("Plugin class missing: {$hit->pluginClass}");
            $this->console->errln('  Use --force-state-clear to drop the state entry anyway.');
            return self::EXIT_NOT_FOUND;
        }
        if (! \is_subclass_of($hit->pluginClass, LifecyclePlugin::class)) {
            $this->console->errln("Package {$package} is a stateless Plugin (no uninstall hook).");
            return self::EXIT_NOT_LIFECYCLE;
        }

        $container = ImanagerBootstrap::create($this->scriptorRoot);
        App::set($container);

        $this->console->writeln('Uninstalling ' . $package . ($purgeData ? ' (--purge-data)' : '') . '...');
        /** @var LifecyclePlugin $instance */
        $instance = new $hit->pluginClass();
        $context  = new PluginContext($container, $instance->name(), purgeDataRequested: $purgeData);
        $instance->uninstall($context);
        $state->unmark($package);
        $this->console->writeln('  ok.');

        $this->console->writeln('');
        $this->console->writeln('State entry removed. Now run:');
        $this->console->writeln('  composer remove ' . $package);
        return self::EXIT_OK;
    }
}
