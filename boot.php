<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\ImanagerBootstrap;

require __DIR__ . '/vendor/autoload.php';

App::set(ImanagerBootstrap::create(__DIR__));

/*
 * Phase 14b-1 boot path:
 *   - vendor/autoload.php (Composer + iManager 2.0)
 *   - iManager container set on App::container()
 *   - $config loaded from data/settings/scriptor-config.php (legacy file
 *     format kept verbatim — themes still read it from $site->config)
 *
 * The legacy 1.x bootstrap (Scriptor/imanager/, editor/core/) is intentionally
 * NOT loaded — it shares the Imanager\ namespace with the new vendor package
 * and would collide. Sub-phases reintroduce functionality piece by piece:
 *   - 14b-2: BasicTheme back on the new container
 *   - 14c:   editor modules (auth, pages, users, settings, install, profile)
 *   - 14f:   delete Scriptor/imanager/ entirely
 */

// Legacy config files guard their first line with `defined('IS_IM')`.
\defined('IS_IM') || \define('IS_IM', true);
// Legacy themes use IM_DATAPATH for theme-config files (BasicTheme in 14b-2).
\defined('IM_DATAPATH') || \define('IM_DATAPATH', __DIR__ . '/data');

/** @var array<string, mixed> $config */
require __DIR__ . '/data/settings/scriptor-config.php';
if (file_exists(__DIR__ . '/data/settings/custom.scriptor-config.php')) {
    $config = array_replace_recursive(
        $config,
        include __DIR__ . '/data/settings/custom.scriptor-config.php',
    );
}
