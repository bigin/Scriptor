<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Frontend\Site;

require_once __DIR__ . '/boot.php';

/** @var array<string, mixed> $config */
$site = new Site(App::container(), $config, __DIR__);
$site->execute();

include __DIR__ . '/site/themes/' . $config['theme_path'] . 'template.php';
