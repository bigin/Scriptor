<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

/**
 * Contract every Scriptor plugin implements.
 *
 * Plugins are composer packages with `"type": "scriptor-plugin"` in their
 * own composer.json plus an `extra.scriptor.plugin` FQCN pointing at an
 * implementor of this interface. The {@see PluginManager} discovers
 * them at boot time and calls {@see register()} on each.
 *
 * `register()` is the only place where the framework hands control to
 * plugin code at boot. Anything the plugin wants to influence (DI
 * bindings, event subscriptions, editor modules, menu items) goes
 * through the {@see PluginContext} passed in. Plugins do not reach
 * into framework internals directly.
 */
interface Plugin
{
    /** Human-readable identifier; shown in logs and the future editor surface. */
    public function name(): string;

    /** Semantic version. Informational only; the loader does not enforce constraints. */
    public function version(): string;

    /**
     * Register the plugin against the application surface. Called once
     * per request, after the iManager container is booted, before any
     * routing or rendering happens.
     */
    public function register(PluginContext $context): void;
}
