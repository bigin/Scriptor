<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events;

use Imanager\Domain\Event\DomainEvent;
use Imanager\Domain\Event\ItemCreated;
use Imanager\Domain\Event\ItemDeleted;
use Imanager\Domain\Event\ItemUpdated;
use Psr\SimpleCache\CacheInterface;

/**
 * Listener for `ItemCreated` / `ItemUpdated` / `ItemDeleted` —
 * flushes the rendered-page cache whenever a page in the target
 * category mutates. Other categories (Users, custom data) don't
 * touch this cache and are ignored.
 *
 * Plan §14e suggests per-URL invalidation, but the cache key in
 * BasicTheme is hashed from `host + REQUEST_URI` and we don't know
 * either of those at event-time. Until the cache adopts tags or
 * per-page prefixes we settle for a global `clear()` of the section
 * cache — heavy-handed but correct, and acceptable because the
 * filesystem cache is currently only used for rendered HTML.
 */
final readonly class PageCacheInvalidationListener
{
    public function __construct(
        private CacheInterface $cache,
        private int $watchedCategoryId,
    ) {}

    public function __invoke(DomainEvent $event): void
    {
        $categoryId = $this->extractCategoryId($event);
        if ($categoryId === null || $categoryId !== $this->watchedCategoryId) {
            return;
        }
        $this->cache->clear();
    }

    private function extractCategoryId(DomainEvent $event): ?int
    {
        return match (true) {
            $event instanceof ItemCreated => $event->item->categoryId,
            $event instanceof ItemUpdated => $event->current->categoryId,
            $event instanceof ItemDeleted => $event->categoryId,
            default                       => null,
        };
    }
}
