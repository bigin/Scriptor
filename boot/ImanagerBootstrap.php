<?php

declare(strict_types=1);

namespace Scriptor\Boot;

use Imanager\Cache\FilesystemCache;
use Imanager\DefaultBootstrap;
use Imanager\Events\SubscriberListenerProvider;
use Imanager\Files\FileStorage;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FileRepository;
use League\Container\Container;
use Scriptor\Boot\Events\ItemFileCleanupListener;
use Scriptor\Boot\Events\PageCacheInvalidationListener;

/**
 * Wires the iManager 2.0 service graph for use inside Scriptor.
 *
 * The library-standard wiring (PDO + schema, repositories, field-type
 * registry, file storage, session store, …) lives in
 * {@see DefaultBootstrap::boot()}. This bootstrap is a thin Scriptor-side
 * shell that defers to it and then registers the Scriptor-specific
 * domain-event listeners on the same container.
 *
 * Paths default to Scriptor's `data/` layout but can be overridden from
 * the caller for tests or alternative installations.
 */
final class ImanagerBootstrap
{
    /**
     * @param array{
     *     databasePath?: string,
     *     uploadsPath?: string,
     *     uploadsUrl?: string,
     *     cachePath?: string,
     * } $paths
     */
    public static function create(string $scriptorRoot, array $paths = []): Container
    {
        $databasePath = $paths['databasePath'] ?? $scriptorRoot . '/data/imanager.db';
        $uploadsPath  = $paths['uploadsPath']  ?? $scriptorRoot . '/data/uploads-2.0';
        $uploadsUrl   = $paths['uploadsUrl']   ?? '/data/uploads-2.0';
        $cachePath    = $paths['cachePath']    ?? $scriptorRoot . '/data/cache/sections';

        $container = DefaultBootstrap::boot(
            databasePath: $databasePath,
            uploadsPath:  $uploadsPath,
            uploadsUrl:   $uploadsUrl,
            cachePath:    $cachePath,
            options:      ['sessionName' => 'scriptor'],
        );

        self::wireDomainEventListeners($container);

        return $container;
    }

    private function __construct()
    {
    }

    /**
     * Subscribes Phase 14e listeners on the container's
     * {@see SubscriberListenerProvider}. Listener instantiation is
     * deferred until the first matching event fires, so a request
     * that doesn't touch any item never even constructs the listeners.
     */
    private static function wireDomainEventListeners(Container $container): void
    {
        /** @var SubscriberListenerProvider $provider */
        $provider = $container->get(SubscriberListenerProvider::class);

        $provider->subscribe(
            \Imanager\Domain\Event\ItemDeleted::class,
            static function (\Imanager\Domain\Event\ItemDeleted $event) use ($container): void {
                /** @var ItemFileCleanupListener $listener */
                static $listener = null;
                if ($listener === null) {
                    $listener = new ItemFileCleanupListener(
                        $container->get(FileRepository::class),
                        $container->get(FileStorage::class),
                    );
                }
                $listener($event);
            },
        );

        // Cache-invalidation: only when a Pages-category item mutates.
        // Resolve the watched id lazily so the listener factory doesn't
        // hit the DB on every request — we resolve once and then keep
        // the same listener instance.
        $cacheInvalidator = static function (object $event) use ($container): void {
            /** @var PageCacheInvalidationListener|null $listener */
            static $listener = null;
            if ($listener === null) {
                $pages = $container->get(CategoryRepository::class)->findBySlug('pages');
                if ($pages === null || $pages->id === null) {
                    return; // no Pages category — nothing to invalidate
                }
                $listener = new PageCacheInvalidationListener(
                    $container->get(FilesystemCache::class),
                    $pages->id,
                );
            }
            $listener($event);
        };
        $provider->subscribe(\Imanager\Domain\Event\ItemCreated::class, $cacheInvalidator);
        $provider->subscribe(\Imanager\Domain\Event\ItemUpdated::class, $cacheInvalidator);
        $provider->subscribe(\Imanager\Domain\Event\ItemDeleted::class, $cacheInvalidator);
    }
}
