<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

use League\Container\Container;

/**
 * Registration surface handed to every plugin's {@see Plugin::register()}.
 *
 * Phase 2 exposes only the DI container. Plugins can bind services
 * (`$context->container()->add(MyService::class)`) and resolve
 * framework services they need. Subsequent phases extend this surface:
 *
 * - Phase 3 adds `subscribe(string $event, callable $handler)` for
 *   PSR-14 frontend event subscriptions.
 * - Phase 4 adds `registerEditorModule()` and `addEditorMenuItem()`
 *   for editor extension hooks.
 *
 * The context mediates every registration so the underlying wiring
 * can change without breaking plugins on every refactor.
 */
final class PluginContext
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function container(): Container
    {
        return $this->container;
    }
}
