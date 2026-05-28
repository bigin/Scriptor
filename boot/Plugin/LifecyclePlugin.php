<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

/**
 * Optional extension of {@see Plugin} for plugins that need install /
 * uninstall hooks beyond per-request `register()`.
 *
 * A plain `Plugin` runs `register()` on every request and that's all.
 * That covers stateless plugins (event subscribers, editor modules,
 * navigation builders) — they have nothing to set up once and nothing
 * to tear down on removal.
 *
 * A `LifecyclePlugin` additionally implements `install()` and
 * `uninstall()` for one-shot work: registering category fields, seeding
 * default items, dropping schema entries on removal. The methods are
 * invoked by `bin/scriptor plugin:install <name>` and
 * `bin/scriptor plugin:uninstall <name>` respectively; the framework
 * itself never auto-invokes them.
 *
 * Conventional `install()` body (real field registration):
 *
 *     public function install(PluginContext $context): void
 *     {
 *         $fields = $context->container()->get(FieldRepository::class);
 *         $categories = $context->container()->get(CategoryRepository::class);
 *         $pagesId = $categories->findBySlug('pages')->id;
 *
 *         $fields->ensure(Field::text($pagesId, 'price',    'Mietpreis')->position(20));
 *         $fields->ensure(Field::text($pagesId, 'deposit',  'Kaution')->position(21));
 *     }
 *
 * Conventional `uninstall()` body (clean removal, data preserved):
 *
 *     public function uninstall(PluginContext $context): void
 *     {
 *         $fields = $context->container()->get(FieldRepository::class);
 *         $pagesId = ...;
 *
 *         // Field definitions removed; the JSON values in items.data
 *         // stay so reinstall picks them up again. Operator can pass
 *         // `--purge-data` to plugin:uninstall to wipe item values too.
 *         foreach (['price', 'deposit'] as $name) {
 *             $f = $fields->findByName($pagesId, $name);
 *             if ($f !== null) {
 *                 $fields->delete($f->id);
 *             }
 *         }
 *     }
 *
 * `LifecyclePlugin` plugins are tracked in `data/plugin-states.json`
 * by {@see PluginStateManager}. Plugins that only implement `Plugin`
 * are not tracked — there's nothing to track since they own no DB
 * state.
 */
interface LifecyclePlugin extends Plugin
{
    /**
     * One-shot setup work: schema registration, default seeds, etc.
     * Invoked by `bin/scriptor plugin:install <package>` and idempotent
     * (the CLI guards against double-install via the state file).
     */
    public function install(PluginContext $context): void;

    /**
     * Inverse of `install()`. Invoked by `bin/scriptor plugin:uninstall
     * <package>`. Convention: remove the plugin's schema entries (field
     * definitions, custom categories) but leave row data in `items.data`
     * untouched, so a later reinstall finds the values waiting. Pass
     * `--purge-data` on the CLI for callers that explicitly want the
     * data gone.
     */
    public function uninstall(PluginContext $context): void;
}
