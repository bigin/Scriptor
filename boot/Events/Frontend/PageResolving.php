<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events\Frontend;

use Imanager\Http\UrlSegments;
use Scriptor\Boot\Frontend\Page;

/**
 * Dispatched at the start of {@see Scriptor\Boot\Frontend\Site::execute()}
 * so plugins can resolve the requested URL to a page before the built-in
 * lookup runs.
 *
 * Listeners cooperate via the mutable {@see $resolution} slot: the first
 * listener to set a non-null Page wins, subsequent listeners are expected
 * to self-check and skip if the slot is already filled. Site picks up
 * whatever is in the slot after all listeners have run and treats it as
 * the resolved page; if the slot is still null, Site falls through to its
 * remaining built-in logic (which in Phase 3 is mostly an empty path
 * because the DB slug lookup has moved into DbPagesResolverPlugin).
 *
 * Conventional listener body:
 *
 *     function (PageResolving $event): void {
 *         if ($event->resolution !== null) return;       // someone already resolved
 *         $page = $this->mySource->find($event->urlSegments);
 *         if ($page !== null) {
 *             $event->resolution = $page;
 *         }
 *     }
 */
final class PageResolving
{
    public ?Page $resolution = null;

    public function __construct(
        public readonly UrlSegments $urlSegments,
    ) {}
}
