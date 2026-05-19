<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Menu;

/**
 * One menu entry in the editor chrome.
 *
 * `displayType` is the layout slot that renders the entry. The two
 * built-in slots are `sidebar` (left-rail navigation in
 * `editor/theme/summary.php`) and `profile` (top-right cluster in
 * `editor/theme/header.php`). A plugin can introduce its own slot by
 * adding a template fragment that reads the same registry with a new
 * displayType string.
 *
 * `href` is optional. When null the layout derives it from the slug
 * (`<siteUrl>/<slug>/`). Pass a literal href for entries that need a
 * suffix, e.g. logout with a CSRF query string.
 */
final readonly class MenuItem
{
    public function __construct(
        public string $slug,
        public string $label,
        public string $icon = '',
        public string $displayType = 'sidebar',
        public int $position = 0,
        public ?string $href = null,
    ) {}
}
