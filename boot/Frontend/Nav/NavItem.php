<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend\Nav;

/**
 * One node in the frontend navigation tree contributed by a plugin.
 *
 * URL is either absolute (`https://...`) or rooted (`/developer-guide/`);
 * the rendering theme is responsible for prepending site URL when it
 * sees a leading slash. Position is the sort key used by
 * {@see FrontendNavRegistry::collect()}: lower wins. Children allow
 * arbitrary nesting; a content tree (markdown pages, blog posts,
 * external links, ...) maps cleanly onto this shape.
 *
 * Active / current state is a render-time concern, not a data
 * concern, so this DTO carries no `isActive` / `isCurrent` flags.
 * The renderer compares each item's URL against the request URL.
 */
final readonly class NavItem
{
    /**
     * @param list<NavItem> $children
     */
    public function __construct(
        public string $url,
        public string $label,
        public int $position = 0,
        public array $children = [],
    ) {}
}
