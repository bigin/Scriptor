<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Frontend\InstallRequiredRenderer;
use Scriptor\Boot\Frontend\Site;

// PHP's built-in server (`php -S … -t public public/index.php`) runs
// this file for every request. Hand existing static files (CSS, JS,
// images, fonts under public/...) back to the server instead of
// bootstrapping the framework. Apache/Caddy/nginx handle this via
// their own try_files rules; the PHP built-in needs the explicit
// `return false`. Branch is a no-op under FPM (PHP_SAPI != cli-server).
if (\PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?? '/';
    $file = __DIR__ . $reqPath;
    // Hand existing static assets back to the server, EXCEPT .php
    // files (only this front controller may run) and dotfiles
    // (.htaccess etc. would otherwise leak via the cli-server's
    // file_get_contents).
    if ($reqPath !== '/'
        && is_file($file)
        && ! str_ends_with(strtolower($reqPath), '.php')
        && ! preg_match('#(^|/)\.[^/]+#', $reqPath)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/boot.php';

/** @var array<string, mixed> $config */
$rootDir   = dirname(__DIR__);
$adminPath = trim((string) ($config['admin_path'] ?? 'editor/'), '/');
$path      = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?? '/';

// Single-entry router. The admin folder (editor/index.php) lives one
// level UP from this webroot, so the only way to reach it is through
// this delegation. Same trick the 2.0 root index.php used; just one
// dir higher.
//
// Wrap the dispatch in a try/catch so a fresh-clone install that
// skipped `bin/scriptor install` lands on a friendly setup page
// instead of a PHP stack trace pointing at PageRepository.php. Any
// other RuntimeException re-throws and falls through to PHP's normal
// error handling.
try {
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
} catch (\RuntimeException $e) {
    if (InstallRequiredRenderer::matches($e)) {
        InstallRequiredRenderer::render();
        return;
    }
    throw $e;
}
