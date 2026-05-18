<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\ImanagerBootstrap;
use Scriptor\Boot\Plugin\PluginManager;

require __DIR__ . '/vendor/autoload.php';

App::set(ImanagerBootstrap::create(__DIR__));

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

$pluginManager = new PluginManager(
    container:  App::container(),
    vendorDir:  __DIR__ . '/vendor',
    cachePath:  __DIR__ . '/data/cache/plugins.php',
    disabled:   (array) ($config['plugins']['disabled'] ?? []),
);
$pluginManager->bootAll();
App::container()->add(PluginManager::class, $pluginManager);
