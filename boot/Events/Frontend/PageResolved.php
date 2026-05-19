<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events\Frontend;

use Scriptor\Boot\Frontend\Page;

/**
 * Dispatched after a page has been resolved (either by a plugin via
 * {@see PageResolving} or by the built-in fall-through). Read-only:
 * subscribers cannot change the result here; they can only react
 * (logging, ACL checks that throw, breadcrumb building).
 */
final readonly class PageResolved
{
    public function __construct(
        public Page $page,
    ) {}
}
