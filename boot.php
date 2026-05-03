<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\ImanagerBootstrap;

require __DIR__ . '/vendor/autoload.php';

App::set(ImanagerBootstrap::create(__DIR__));

/*
 * Phase 14a status: only the iManager 2.0 container is wired up.
 *
 * The legacy 1.x bootstrap below is intentionally disabled — the embedded
 * Scriptor/imanager/ library shares the Imanager\ namespace with the new
 * vendor/bigins/imanager package, so loading both at once collides.
 *
 * Sub-phases re-enable functionality piece by piece:
 *   - 14b: Site / Page / Pages frontend on the new container
 *   - 14c: Editor modules (auth, pages, users, settings, install, profile)
 *   - 14f: delete Scriptor/imanager/ entirely
 *
 * Until 14b lands, the public site and editor are non-functional on this
 * branch. Use /test-bootstrap.php to verify the container, or check out
 * `master` for the working 1.x build.
 */
