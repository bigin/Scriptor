<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Frontend\Site;

require_once dirname(__DIR__) . '/boot.php';

/** @var array<string, mixed> $config */
$rootDir   = dirname(__DIR__);
$adminPath = trim((string) ($config['admin_path'] ?? 'editor/'), '/');
$path      = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?? '/';

// Single-entry router. The admin folder (editor/index.php) lives one
// level UP from this webroot, so the only way to reach it is through
// this delegation. Same trick the 2.0 root index.php used; just one
// dir higher.
if ($path === '/' . $adminPath || str_starts_with($path, '/' . $adminPath . '/')) {
    require $rootDir . '/' . $adminPath . '/index.php';
    return;
}

$themeRoot = $rootDir . '/themes/' . trim((string) $config['theme_path'], '/');
$ext       = $themeRoot . '/_ext.php';

if (file_exists($ext)) {
    // Theme installs its own $site (typically a Site subclass) and router.
    $site = null;
    include $ext;
    if (! $site instanceof Site) {
        // Cache short-circuit returned early; nothing left to render.
        return;
    }
} else {
    $site = new Site(App::container(), $config, $rootDir);
    $site->execute();
}

include $themeRoot . '/template.php';
