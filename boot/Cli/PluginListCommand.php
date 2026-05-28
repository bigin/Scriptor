<?php

declare(strict_types=1);

namespace Scriptor\Boot\Cli;

use League\Container\Container;
use Scriptor\Boot\Plugin\LifecyclePlugin;
use Scriptor\Boot\Plugin\Plugin;
use Scriptor\Boot\Plugin\PluginManager;
use Scriptor\Boot\Plugin\PluginStateManager;

/**
 * `bin/scriptor plugin:list` implementation.
 *
 * Walks composer's installed.json (via PluginManager::discover) and
 * the lifecycle state file (via PluginStateManager) and prints a
 * three-column overview: package name, version, kind/status. The
 * kind/status combines two orthogonal pieces of info:
 *
 *   - **kind**: `stateless` for plain `Plugin` implementors,
 *     `lifecycle` for `LifecyclePlugin` (one with install()/uninstall()).
 *   - **status**: `installed` (lifecycle plugin tracked in state.json),
 *     `pending install` (lifecycle plugin discovered but not yet
 *     installed via plugin:install), `discovered` (stateless), or
 *     `ORPHAN` (state.json entry without a discovered package — the
 *     plugin was composer-removed without a plugin:uninstall first).
 *
 * Read-only: no DB access, no state mutation, no plugin boot. Safe to
 * run against a fresh checkout where data/imanager.db does not exist
 * yet — it only reads vendor/composer/installed.json and
 * data/plugin-states.json (the latter is treated as empty when absent).
 */
final class PluginListCommand
{
    private const EXIT_OK         = 0;
    private const EXIT_UNEXPECTED = 4;

    public function __construct(
        private readonly string $scriptorRoot,
        private readonly Console $console,
    ) {}

    public function run(): int
    {
        try {
            return $this->runUnsafe();
        } catch (\Throwable $e) {
            $this->console->errln('plugin:list failed: ' . $e->getMessage());
            return self::EXIT_UNEXPECTED;
        }
    }

    private function runUnsafe(): int
    {
        $manager = new PluginManager(
            container:   new Container(),
            vendorDir:   $this->scriptorRoot . '/vendor',
            cachePath:   $this->scriptorRoot . '/data/cache/plugins.php',
            disabled:    [],
            corePlugins: [],
        );
        $state = new PluginStateManager($this->scriptorRoot);

        $manifests = $manager->discover(forceRefresh: true);
        $stateEntries = $state->all();

        $rows = [];
        foreach ($manifests as $manifest) {
            $class = $manifest->pluginClass;
            $kind  = 'stateless';
            $status = 'discovered';
            if (\class_exists($class) && \is_subclass_of($class, LifecyclePlugin::class)) {
                $kind = 'lifecycle';
                $status = $state->isInstalled($manifest->packageName)
                    ? 'installed'
                    : 'pending install';
            }
            $rows[] = [
                'name'    => $manifest->packageName,
                'version' => $manifest->packageVersion,
                'kind'    => $kind,
                'status'  => $status,
            ];
        }

        // Orphans: state entries with no matching discovered manifest.
        $discoveredNames = \array_map(static fn ($m) => $m->packageName, $manifests);
        foreach ($stateEntries as $name => $entry) {
            if (\in_array($name, $discoveredNames, true)) {
                continue;
            }
            $rows[] = [
                'name'    => $name,
                'version' => (string) ($entry['version'] ?? '?'),
                'kind'    => 'lifecycle',
                'status'  => 'ORPHAN',
            ];
        }

        if ($rows === []) {
            $this->console->writeln('No scriptor plugins discovered.');
            $this->console->writeln('(install one with `composer require <package>`)');
            return self::EXIT_OK;
        }

        \usort($rows, static fn ($a, $b) => $a['name'] <=> $b['name']);

        $widths = [
            'name'    => \max(7,  \max(\array_map(static fn ($r) => \strlen($r['name']),    $rows))),
            'version' => \max(7,  \max(\array_map(static fn ($r) => \strlen($r['version']), $rows))),
            'kind'    => \max(4,  \max(\array_map(static fn ($r) => \strlen($r['kind']),    $rows))),
            'status'  => \max(6,  \max(\array_map(static fn ($r) => \strlen($r['status']),  $rows))),
        ];

        $line = static fn (array $r): string => \sprintf(
            "  %-{$widths['name']}s  %-{$widths['version']}s  %-{$widths['kind']}s  %s",
            $r['name'], $r['version'], $r['kind'], $r['status'],
        );

        $this->console->writeln($line([
            'name' => 'PACKAGE', 'version' => 'VERSION', 'kind' => 'KIND', 'status' => 'STATUS',
        ]));
        $this->console->writeln(\str_repeat('  ', 1) . \str_repeat('-', $widths['name'] + $widths['version'] + $widths['kind'] + $widths['status'] + 6));
        foreach ($rows as $r) {
            $this->console->writeln($line($r));
        }

        $orphans = \count(\array_filter($rows, static fn ($r) => $r['status'] === 'ORPHAN'));
        $pending = \count(\array_filter($rows, static fn ($r) => $r['status'] === 'pending install'));
        $this->console->writeln('');
        $this->console->writeln(\sprintf(
            '%d plugin(s); %d pending install, %d orphan(s).',
            \count($rows), $pending, $orphans,
        ));
        if ($pending > 0) {
            $this->console->writeln('Run `bin/scriptor plugin:install <package>` to invoke the install hook.');
        }
        if ($orphans > 0) {
            $this->console->writeln('Run `bin/scriptor plugin:cleanup-orphan <package>` to clear stale state entries.');
        }

        return self::EXIT_OK;
    }
}
