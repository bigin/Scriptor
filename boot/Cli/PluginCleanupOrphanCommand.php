<?php

declare(strict_types=1);

namespace Scriptor\Boot\Cli;

use League\Container\Container;
use Scriptor\Boot\Plugin\PluginManager;
use Scriptor\Boot\Plugin\PluginStateManager;

/**
 * `bin/scriptor plugin:cleanup-orphan <package>` implementation.
 *
 * Removes the state.json entry for a package that's marked installed
 * but no longer has a discovered manifest in vendor/. This is the
 * "I forgot to run plugin:uninstall before composer remove" recovery
 * path.
 *
 * Schema entries the orphaned plugin registered (field definitions,
 * custom categories, etc.) are NOT touched — the plugin's code is
 * gone so we don't know what those entries were. The operator can
 * either:
 *   - reinstall the plugin (composer require + plugin:install) and
 *     then do a clean plugin:uninstall, OR
 *   - clean up the schema entries manually via direct SQL or the
 *     iManager Fields editor.
 *
 * Without `<package>`, the command lists every orphan and exits
 * without mutating state — so operators can review before clearing.
 */
final class PluginCleanupOrphanCommand
{
    private const EXIT_OK         = 0;
    private const EXIT_NOT_ORPHAN = 1;
    private const EXIT_UNEXPECTED = 4;

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
            $this->console->errln('plugin:cleanup-orphan failed: ' . $e->getMessage());
            return self::EXIT_UNEXPECTED;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string>         $positional
     */
    private function runUnsafe(array $options, array $positional): int
    {
        $state = new PluginStateManager($this->scriptorRoot);
        $manager = new PluginManager(
            container:   new Container(),
            vendorDir:   $this->scriptorRoot . '/vendor',
            cachePath:   $this->scriptorRoot . '/data/cache/plugins.php',
            disabled:    [],
            corePlugins: [],
        );

        $orphans = $manager->orphans($state);

        if ($positional === []) {
            if ($orphans === []) {
                $this->console->writeln('No orphan plugins.');
                return self::EXIT_OK;
            }
            $this->console->writeln(\sprintf('%d orphan plugin(s):', \count($orphans)));
            foreach ($orphans as $name) {
                $entry = $state->get($name);
                $this->console->writeln('  - ' . $name . ' (v' . ($entry['version'] ?? '?') . ')');
            }
            $this->console->writeln('');
            $this->console->writeln('Run with <package> to clear a specific entry.');
            return self::EXIT_OK;
        }

        if (\count($positional) !== 1) {
            $this->console->errln('Expected at most one <package> argument.');
            return self::EXIT_NOT_ORPHAN;
        }
        $package = $positional[0];

        if (! \in_array($package, $orphans, true)) {
            $this->console->errln("Package {$package} is not an orphan.");
            if ($state->isInstalled($package)) {
                $this->console->errln('  It is still discovered — use `plugin:uninstall` to remove it cleanly.');
            } else {
                $this->console->errln('  It is not in the state file at all.');
            }
            return self::EXIT_NOT_ORPHAN;
        }

        $state->unmark($package);
        $this->console->writeln("Cleared orphan state entry for {$package}.");
        $this->console->writeln('NOTE: any schema entries the plugin registered (field');
        $this->console->writeln('definitions, categories) remain in the DB. Reinstall the');
        $this->console->writeln('plugin and run a clean plugin:uninstall to remove them,');
        $this->console->writeln('or clean them up manually.');
        return self::EXIT_OK;
    }
}
