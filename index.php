<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Frontend\Site;

require_once __DIR__ . '/boot.php';

/** @var array<string, mixed> $config */
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
