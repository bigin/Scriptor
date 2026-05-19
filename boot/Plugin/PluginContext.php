<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

use Imanager\Events\SubscriberListenerProvider;
use League\Container\Container;

/**
 * Registration surface handed to every plugin's {@see Plugin::register()}.
 *
 * Phase 2 exposed the DI container. Phase 3 adds event subscriptions
 * (PSR-14) via {@see subscribe()}; the underlying listener provider is
 * the same one iManager wires for its storage events, so a plugin can
 * subscribe to Scriptor frontend events and iManager storage events
 * with the same call.
 *
 * Subsequent phases extend this surface:
 *
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

    /**
     * Subscribe to an event by class name. The handler runs for every
     * dispatched event that is an instance of `$eventClass` (or a
     * subclass), in registration order.
     *
     * `$handler` should declare its concrete event class in its
     * signature; PHP type-checks at call time. The plain `callable`
     * type here keeps PHPStan happy under callable-variance.
     *
     * @param class-string $eventClass
     */
    public function subscribe(string $eventClass, callable $handler): void
    {
        /** @var SubscriberListenerProvider $provider */
        $provider = $this->container->get(SubscriberListenerProvider::class);
        $provider->subscribe($eventClass, $handler);
    }
}
