<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Frontend\Site;

require_once __DIR__ . '/boot.php';

/** @var array<string, mixed> $config */
$adminPath = trim((string) ($config['admin_path'] ?? 'editor/'), '/');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?? '/';

// Single-entry router: hand off /<admin_path>/* requests to the editor
// entry. Keeps the site working on web servers that route everything
// through index.php (Caddy default snippet, php -S) without depending
// on .htaccess-style rewrites being honoured.
if ($path === '/' . $adminPath || str_starts_with($path, '/' . $adminPath . '/')) {
    require __DIR__ . '/' . $adminPath . '/index.php';
    return;
}

$themeDir = __DIR__ . '/site/themes/' . $config['theme_path'];
$ext      = $themeDir . '_ext.php';

if (file_exists($ext)) {
    // Theme installs its own $site (typically a Site subclass) and router.
    $site = null;
    include $ext;
    if (! $site instanceof Site) {
        // Cache short-circuit returned early; nothing left to render.
        return;
    }
} else {
    $site = new Site(App::container(), $config, __DIR__);
    $site->execute();
}

include $themeDir . 'template.php';
