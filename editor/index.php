<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\EditorRouter;

require_once __DIR__ . '/../boot.php';

if (! isset($_SESSION)) {
    session_name('IMSESSID');
    // X-Forwarded-Proto so the cookie picks up Secure when sitting
    // behind a TLS-terminating reverse proxy (nginx-proxy on Hetzner,
    // Caddy on the live site). Falls back to direct HTTPS / off for
    // local dev (http://scriptor.cms via ServBay).
    $proto  = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
    $secure = $proto === 'https'
        || (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** @var array<string, mixed> $config */
$editor = new Editor(App::container(), $config, dirname(__DIR__));
$router = new EditorRouter($editor, App::container());
$router->execute();

include __DIR__ . '/theme/template.php';
