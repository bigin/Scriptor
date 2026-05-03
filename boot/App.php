<?php

declare(strict_types=1);

namespace Scriptor\Boot;

use League\Container\Container;

/**
 * Process-wide service-locator handle for the iManager 2.0 container.
 *
 * Phase 14a needs a way for legacy Scriptor code to reach the new
 * container without threading it through every constructor in one PR.
 * Subsequent sub-phases (14b–14c) replace `App::container()->get(...)`
 * call-sites with explicit dependency injection module by module, and
 * this locator becomes empty at the end of Phase 14.
 */
final class App
{
    private static ?Container $container = null;

    public static function set(Container $container): void
    {
        self::$container = $container;
    }

    public static function container(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException(
                'iManager container has not been booted yet. Did boot.php run?',
            );
        }
        return self::$container;
    }

    public static function reset(): void
    {
        self::$container = null;
    }

    private function __construct()
    {
    }
}
