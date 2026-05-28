<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

use Imanager\Events\SubscriberListenerProvider;
use Imanager\Http\UrlSegments;
use League\Container\Container;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\Menu\MenuItem;
use Scriptor\Boot\Editor\Menu\MenuRegistry;
use Scriptor\Boot\Editor\Module;
use Scriptor\Boot\Editor\ModuleRegistry;
use Scriptor\Boot\Frontend\Nav\FrontendNavRegistry;
use Scriptor\Boot\Frontend\Nav\NavItem;

/**
 * Registration surface handed to every plugin's {@see Plugin::register()}.
 *
 * Phase 2 exposed the DI container.
 * Phase 3 added {@see subscribe()} for PSR-14 frontend events.
 * Phase 4 added {@see registerEditorModule()} and
 * {@see addEditorMenuItem()} for editor extension hooks.
 *
 * Each call to a registration method is recorded on the context
 * itself ({@see registrations()}) so the new InstalledPluginsModule
 * can show operators which plugin contributed which surface. The
 * actual registration still goes to the same global registries; the
 * tracking is a side-channel for diagnostics.
 *
 * PluginManager constructs a separate context per plugin (instead of
 * sharing one) so each plugin's registrations are isolated.
 */
final class PluginContext
{
    /** @var list<string> Event class names this plugin subscribed to. */
    private array $events = [];

    /** @var list<string> Editor module slugs this plugin registered. */
    private array $modules = [];

    /** @var list<MenuItem> Editor menu items this plugin added. */
    private array $menuItems = [];

    /** @var int Frontend nav builders this plugin contributed. */
    private int $navBuilderCount = 0;

    public function __construct(
        private readonly Container $container,
        public readonly string $pluginName = '',
        /**
         * Set to true when the context is handed to a
         * {@see LifecyclePlugin::uninstall()} invocation triggered by
         * `bin/scriptor plugin:uninstall --purge-data`. Plugins read
         * this to decide whether to also remove the row values they
         * own from `items.data` (default: preserve, on the assumption
         * that the operator may reinstall later).
         *
         * Always `false` during the normal `register()` boot path and
         * during install() — the flag only carries meaning inside
         * uninstall().
         */
        public readonly bool $purgeDataRequested = false,
    ) {}

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Subscribe to an event by class name. The handler runs for every
     * dispatched event that is an instance of `$eventClass` (or a
     * subclass), in registration order.
     *
     * @param class-string $eventClass
     */
    public function subscribe(string $eventClass, callable $handler): void
    {
        $this->events[] = $eventClass;
        /** @var SubscriberListenerProvider $provider */
        $provider = $this->container->get(SubscriberListenerProvider::class);
        $provider->subscribe($eventClass, $handler);
    }

    /**
     * Register an editor module under `/editor/<slug>/...`. The factory
     * is called per request once the auth gate (if any) has passed.
     * Override semantics: re-registering an existing slug replaces the
     * previous factory.
     *
     * @param callable(Container, Editor): Module $factory
     */
    public function registerEditorModule(string $slug, callable $factory): void
    {
        $this->modules[] = $slug;
        /** @var ModuleRegistry $registry */
        $registry = $this->container->get(ModuleRegistry::class);
        $registry->register($slug, $factory);
    }

    /**
     * Add an entry to the editor chrome (sidebar or profile cluster,
     * depending on `$item->displayType`). Items render in ascending
     * `position` order then by registration order.
     */
    public function addEditorMenuItem(MenuItem $item): void
    {
        $this->menuItems[] = $item;
        /** @var MenuRegistry $registry */
        $registry = $this->container->get(MenuRegistry::class);
        $registry->add($item);
    }

    /**
     * Contribute a frontend nav builder. The builder runs once per
     * request with the current {@see UrlSegments} and returns a list
     * of top-level {@see NavItem} nodes (optionally nested via
     * `NavItem::$children`). Themes that consume the frontend nav
     * ask {@see FrontendNavRegistry::collect()} for the merged tree;
     * the registry sorts entries by `position` then registration
     * order.
     *
     * Plugins that own a content tree (markdown pages, blog posts,
     * external links, ...) should contribute through this hook
     * instead of asking themes to walk plugin-owned directories.
     *
     * @param callable(UrlSegments): array<int, NavItem> $builder
     */
    public function contributeFrontendNav(callable $builder): void
    {
        $this->navBuilderCount++;
        /** @var FrontendNavRegistry $registry */
        $registry = $this->container->get(FrontendNavRegistry::class);
        $registry->contribute($builder);
    }

    /**
     * Snapshot of what this plugin registered. Used by the editor's
     * InstalledPluginsModule to render a per-plugin breakdown.
     *
     * @return array{events: list<string>, modules: list<string>, menuItems: list<MenuItem>, navBuilders: int}
     */
    public function registrations(): array
    {
        return [
            'events'      => $this->events,
            'modules'     => $this->modules,
            'menuItems'   => $this->menuItems,
            'navBuilders' => $this->navBuilderCount,
        ];
    }
}
