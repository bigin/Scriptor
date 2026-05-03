<?php

declare(strict_types=1);

namespace Scriptor\Boot;

use Imanager\Bootstrap;
use Imanager\Cache\FilesystemCache;
use Imanager\Events\SubscriberListenerProvider;
use Imanager\Events\SyncEventDispatcher;
use Imanager\Field\FieldTypeRegistry;
use Imanager\Field\Types\ArrayListFieldType;
use Imanager\Field\Types\CheckboxFieldType;
use Imanager\Field\Types\DatepickerFieldType;
use Imanager\Field\Types\DecimalFieldType;
use Imanager\Field\Types\DropdownFieldType;
use Imanager\Field\Types\EditorFieldType;
use Imanager\Field\Types\FilepickerFieldType;
use Imanager\Field\Types\FileuploadFieldType;
use Imanager\Field\Types\HiddenFieldType;
use Imanager\Field\Types\ImageuploadFieldType;
use Imanager\Field\Types\IntegerFieldType;
use Imanager\Field\Types\LongTextFieldType;
use Imanager\Field\Types\MoneyFieldType;
use Imanager\Field\Types\PasswordFieldType;
use Imanager\Field\Types\SlugFieldType;
use Imanager\Field\Types\TextFieldType;
use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessor;
use Imanager\Files\LocalFileStorage;
use Imanager\Http\Csrf;
use Imanager\Http\NativeSessionStore;
use Imanager\Http\SessionStore;
use Imanager\Search\FullTextSearch;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\FileRepository;
use Imanager\Storage\ItemRepository;
use Imanager\Storage\SchemaManager;
use Imanager\Storage\Sqlite\ConnectionFactory;
use Imanager\Storage\Sqlite\MigrationLoader;
use Imanager\Storage\Sqlite\SqliteStorage;
use Imanager\Storage\Storage;
use Imanager\Validation\Sanitizer;
use League\Container\Container;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Scriptor\Boot\Events\ItemFileCleanupListener;
use Scriptor\Boot\Events\PageCacheInvalidationListener;

/**
 * Wires the iManager 2.0 service graph for use inside Scriptor.
 *
 * The container produced here is what `boot.php` exposes as the canonical
 * service locator for the rest of the legacy Scriptor code (Phase 14a) and,
 * over the course of Phase 14b–14f, the modernised replacements.
 *
 * Paths default to Scriptor's `data/` layout but can be overridden from the
 * caller for tests or alternative installations.
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
        $schemaDir    = $scriptorRoot . '/vendor/bigins/imanager/config/schema';

        $container = Bootstrap::boot();

        $container->addShared(\PDO::class, static function () use ($databasePath, $schemaDir): \PDO {
            $pdo = (new ConnectionFactory($databasePath))->create();
            $loader = new MigrationLoader($schemaDir);
            (new SchemaManager($pdo, $loader->load()))->migrate();
            return $pdo;
        });

        $container->addShared(Sanitizer::class, static fn(): Sanitizer => new Sanitizer());

        // 14e: PSR-14 dispatcher + listener provider. The same provider
        // instance is reachable via both interfaces so listeners can
        // subscribe through the typed bind, while storage and other
        // dispatch sites use the dispatcher.
        $container->addShared(SubscriberListenerProvider::class, static fn(): SubscriberListenerProvider
            => new SubscriberListenerProvider());
        $container->addShared(ListenerProviderInterface::class, static fn(): ListenerProviderInterface
            => $container->get(SubscriberListenerProvider::class));
        $container->addShared(EventDispatcherInterface::class, static fn(): EventDispatcherInterface
            => new SyncEventDispatcher($container->get(ListenerProviderInterface::class)));

        $container->addShared(SqliteStorage::class, static fn(): SqliteStorage
            => new SqliteStorage(
                $container->get(\PDO::class),
                $container->get(EventDispatcherInterface::class),
            ));
        $container->addShared(Storage::class, static fn(): Storage
            => $container->get(SqliteStorage::class));

        $container->addShared(CategoryRepository::class, static fn(): CategoryRepository
            => $container->get(SqliteStorage::class)->categories());
        $container->addShared(FieldRepository::class, static fn(): FieldRepository
            => $container->get(SqliteStorage::class)->fields());
        $container->addShared(ItemRepository::class, static fn(): ItemRepository
            => $container->get(SqliteStorage::class)->items());
        $container->addShared(FileRepository::class, static fn(): FileRepository
            => $container->get(SqliteStorage::class)->files());

        $container->addShared(FullTextSearch::class, static fn(): FullTextSearch
            => new FullTextSearch($container->get(\PDO::class)));

        $container->addShared(FilesystemCache::class, static fn(): FilesystemCache
            => new FilesystemCache($cachePath));

        $container->addShared(LocalFileStorage::class, static fn(): LocalFileStorage
            => new LocalFileStorage($uploadsPath, $uploadsUrl));
        $container->addShared(FileStorage::class, static fn(): FileStorage
            => $container->get(LocalFileStorage::class));

        $container->addShared(ImageProcessor::class, static fn(): ImageProcessor
            => ImageProcessor::default());

        $container->addShared(SessionStore::class, static fn(): SessionStore
            => new NativeSessionStore('scriptor'));
        $container->addShared(Csrf::class, static fn(): Csrf
            => new Csrf($container->get(SessionStore::class), maxTokens: 10));

        // 14e domain-event listeners. Side effects fire in-process from
        // SqliteStorage save/delete (synchronous PSR-14 dispatch).
        self::wireDomainEventListeners($container);

        $container->addShared(FieldTypeRegistry::class, static function () use ($container): FieldTypeRegistry {
            $registry = new FieldTypeRegistry();
            $sanitizer = $container->get(Sanitizer::class);
            $registry->register(new TextFieldType($sanitizer));
            $registry->register(new LongTextFieldType($sanitizer));
            $registry->register(new EditorFieldType($sanitizer));
            $registry->register(new SlugFieldType($sanitizer));
            $registry->register(new PasswordFieldType($sanitizer));
            $registry->register(new IntegerFieldType($sanitizer));
            $registry->register(new DecimalFieldType($sanitizer));
            $registry->register(new MoneyFieldType($sanitizer));
            $registry->register(new CheckboxFieldType($sanitizer));
            $registry->register(new DropdownFieldType($sanitizer));
            $registry->register(new DatepickerFieldType($sanitizer));
            $registry->register(new HiddenFieldType($sanitizer));
            $registry->register(new ArrayListFieldType($sanitizer));
            $registry->register(new FileuploadFieldType($sanitizer));
            $registry->register(new ImageuploadFieldType($sanitizer));
            $registry->register(new FilepickerFieldType($sanitizer));
            return $registry;
        });

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
