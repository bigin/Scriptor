<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Editor\Menu\MenuRegistry;
use Scriptor\Boot\Editor\ModuleRegistry;
use Scriptor\Boot\ImanagerBootstrap;
use Scriptor\Boot\Plugin\PluginManager;

require __DIR__ . '/vendor/autoload.php';

App::set(ImanagerBootstrap::create(__DIR__));

// Scriptor's frontend Page repository sits one layer above iManager's
// storage repositories. Bind it once so anything in the request
// pipeline (Site, plugins, modules) can resolve the same instance via
// the container instead of newing one up themselves.
App::container()->addShared(
    \Scriptor\Boot\Frontend\PageRepository::class,
    static fn(): \Scriptor\Boot\Frontend\PageRepository
        => new \Scriptor\Boot\Frontend\PageRepository(
            App::container()->get(\Imanager\Storage\CategoryRepository::class),
            App::container()->get(\Imanager\Storage\ItemRepository::class),
        ),
);

// Editor extension registries. Created empty here so the CoreEditorPlugin
// and any third-party plugin can populate them during Plugin::register().
// EditorRouter reads ModuleRegistry to dispatch; the editor layout
// templates read MenuRegistry to render the sidebar and profile cluster.
App::container()->addShared(ModuleRegistry::class, static fn() => new ModuleRegistry());
App::container()->addShared(MenuRegistry::class,   static fn() => new MenuRegistry());

/*
 * Boot path:
 *   - vendor/autoload.php (Composer + iManager 2.0)
 *   - iManager container set on App::container()
 *   - $config loaded from data/settings/scriptor-config.php (file format
 *     unchanged; themes still read it from $site->config)
 *   - Plugin discovery + boot. Plugins register against PluginContext
 *     before any routing or rendering. See docs/scriptor-plugin-api-plan.md.
 */

// scriptor-config.php still guards its first line with `defined('IS_IM')`,
// and the bundled basic-theme-config.php builds asset paths from
// IM_DATAPATH. Both are tiny config files; keeping the constants saves
// the user from rewriting their data/settings/* files on upgrade.
\defined('IS_IM') || \define('IS_IM', true);
\defined('IM_DATAPATH') || \define('IM_DATAPATH', __DIR__ . '/data');

/** @var array<string, mixed> $config */
require __DIR__ . '/data/settings/scriptor-config.php';
if (file_exists(__DIR__ . '/data/settings/custom.scriptor-config.php')) {
    $config = array_replace_recursive(
        $config,
        include __DIR__ . '/data/settings/custom.scriptor-config.php',
    );
}

// Make the resolved config and the Scriptor root reachable via the
// container so plugins can read them at register() time, before
// per-request services like Editor or Site exist.
App::container()->add('scriptor.config', $config);
App::container()->add('scriptor.root',   __DIR__);

$pluginManager = new PluginManager(
    container:   App::container(),
    vendorDir:   __DIR__ . '/vendor',
    cachePath:   __DIR__ . '/data/cache/plugins.php',
    disabled:    (array) ($config['plugins']['disabled'] ?? []),
    corePlugins: [
        // Built-in DB slug resolver. Subscribes to PageResolving and
        // runs the same DB-backed lookup the old Site::execute had.
        \Scriptor\Boot\Plugin\CorePlugins\DbPagesResolverPlugin::class,

        // Built-in editor surfaces (pages, profile, auth, settings,
        // install). Seeds ModuleRegistry + MenuRegistry from config.
        \Scriptor\Boot\Plugin\CorePlugins\CoreEditorPlugin::class,
    ],
);
$pluginManager->bootAll();
App::container()->add(PluginManager::class, $pluginManager);
