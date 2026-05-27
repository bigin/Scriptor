<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin\CorePlugins;

use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\FileRepository;
use Imanager\Storage\ItemRepository;
use League\Container\Container;
use Scriptor\Boot\Editor\Auth\AuthModule;
use Scriptor\Boot\Editor\Auth\LoginAttempts;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\Menu\MenuItem;
use Scriptor\Boot\Editor\Module;
use Scriptor\Boot\Editor\Pages\PagesModule;
use Scriptor\Boot\Editor\Plugins\PluginsModule;
use Scriptor\Boot\Editor\Profile\ProfileModule;
use Scriptor\Boot\Editor\Settings\SettingsModule;
use Scriptor\Boot\Editor\UserRepository;
use Scriptor\Boot\Frontend\PageRepository;
use Scriptor\Boot\Plugin\Plugin;
use Scriptor\Boot\Plugin\PluginContext;
use Scriptor\Boot\Plugin\PluginManager;

/**
 * Registers the five built-in editor modules through the same
 * {@see ModuleRegistry} / {@see MenuRegistry} surface that third-party
 * plugins use. The {@see EditorRouter} no longer if-ladders over hard-
 * coded slugs; it walks the registry instead.
 *
 * Menu items mirror what `data/settings/scriptor-config.php` (`modules`
 * key) used to drive directly from `summary.php` / `header.php`. The
 * config is still the source of truth for label / icon / position /
 * display_type, so operators who have customised the menu in their own
 * config keep their changes. Plugins additionally call
 * `$context->addEditorMenuItem(...)` to extend.
 */
final class CoreEditorPlugin implements Plugin
{
    public function name(): string
    {
        return 'core/editor';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(PluginContext $context): void
    {
        // Module factories. Each closure resolves its own dependencies
        // from the container; Editor is passed positionally because it
        // is constructed in editor/index.php after the plugin manager
        // has already booted.

        $context->registerEditorModule('pages', static fn (Container $c, Editor $e): Module
            => new PagesModule(
                $e,
                $c->get(PageRepository::class),
                $c->get(FieldRepository::class),
                $c->get(FileRepository::class),
                $c->get(\Psr\EventDispatcher\EventDispatcherInterface::class),
            ));

        $context->registerEditorModule('profile', static fn (Container $c, Editor $e): Module
            => new ProfileModule(
                $e,
                new UserRepository(
                    $c->get(CategoryRepository::class),
                    $c->get(ItemRepository::class),
                ),
            ));

        $context->registerEditorModule('auth', static fn (Container $c, Editor $e): Module
            => new AuthModule(
                $e,
                new UserRepository(
                    $c->get(CategoryRepository::class),
                    $c->get(ItemRepository::class),
                ),
                new LoginAttempts(
                    $e->session,
                    maxAttempts: (int) ($e->config['maxFailedAccessAttempts'] ?? 5),
                    lockoutMinutes: (int) ($e->config['accessLockoutDuration'] ?? 5),
                ),
            ));

        $context->registerEditorModule('settings', static fn (Container $c, Editor $e): Module
            => new SettingsModule($e));

        // Plugins module: replaces the legacy site/modules/* InstallModule
        // with a read-only browser over the Composer-discovered Scriptor
        // plugins (the new Plugin API surface). Operators install plugins
        // via `composer require` and disable them through the
        // `plugins.disabled` config key.
        $context->registerEditorModule('plugins', static fn (Container $c, Editor $e): Module
            => new PluginsModule($e, $c->get(PluginManager::class)));

        // The api/upload endpoint is not a Module (it's a JSON
        // endpoint, not a layout-bearing page), so it does NOT
        // register here. EditorRouter keeps the api/* dispatch
        // inline. If a future plugin contributes more api endpoints,
        // we extract an ApiEndpointRegistry then.

        // Seed the menu registry from the config['modules'] entries.
        // This preserves the existing menu-driven layout exactly: any
        // operator who tuned position/icon/menu/display_type in their
        // own config sees the same chrome as before.
        $config  = $context->container()->get('scriptor.config');
        $modules = (array) ($config['modules'] ?? []);
        foreach ($modules as $slug => $entry) {
            if (! is_array($entry) || empty($entry['active'])) {
                continue;
            }
            $displayTypes = (array) ($entry['display_type'] ?? []);
            $menuKey      = (string) ($entry['menu'] ?? '');
            $icon         = (string) ($entry['icon'] ?? '');
            $position     = (int) ($entry['position'] ?? 0);

            foreach ($displayTypes as $displayType) {
                if (! is_string($displayType) || $displayType === '') {
                    continue;
                }
                $context->addEditorMenuItem(new MenuItem(
                    slug:        (string) $slug,
                    label:       $menuKey,
                    icon:        $icon,
                    displayType: $displayType,
                    position:    $position,
                ));
            }
        }

    }
}
