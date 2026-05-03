<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\EditorRouter;

require_once __DIR__ . '/../boot.php';

if (! isset($_SESSION)) {
    session_name('IMSESSID');
    session_start();
}

/** @var array<string, mixed> $config */
$editor = new Editor(App::container(), $config, dirname(__DIR__));
$router = new EditorRouter($editor, App::container());
$router->execute();

include __DIR__ . '/theme/template.php';
