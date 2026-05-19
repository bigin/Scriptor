<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

use Imanager\Events\SubscriberListenerProvider;
use League\Container\Container;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\Menu\MenuItem;
use Scriptor\Boot\Editor\Menu\MenuRegistry;
use Scriptor\Boot\Editor\Module;
use Scriptor\Boot\Editor\ModuleRegistry;

/**
 * Registration surface handed to every plugin's {@see Plugin::register()}.
 *
 * Phase 2 exposed the DI container.
 * Phase 3 added {@see subscribe()} for PSR-14 frontend events.
 * Phase 4 adds {@see registerEditorModule()} and
 * {@see addEditorMenuItem()} so plugins can plug their own surfaces
 * into the editor.
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

    /**
     * Subscribe to an event by class name. The handler runs for every
     * dispatched event that is an instance of `$eventClass` (or a
     * subclass), in registration order.
     *
     * @param class-string $eventClass
     */
    public function subscribe(string $eventClass, callable $handler): void
    {
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
        /** @var MenuRegistry $registry */
        $registry = $this->container->get(MenuRegistry::class);
        $registry->add($item);
    }
}
