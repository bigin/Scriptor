<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend\Nav;

use Imanager\Http\UrlSegments;

/**
 * Plugin-contributed frontend navigation. Plugins register builder
 * callables via {@see \Scriptor\Boot\Plugin\PluginContext::contributeFrontendNav()};
 * each builder is called once per request with the current
 * {@see UrlSegments} and returns a list of top-level {@see NavItem}
 * nodes (possibly nested).
 *
 * The registry produces a flat list of top-level NavItems at
 * `collect()` time, sorted by ascending position and stable on
 * registration order for ties. The theme renders the list; active /
 * current state is the theme's concern.
 *
 * Why a builder callable instead of a static NavItem list:
 *
 * - A plugin's nav often depends on the request URL (for example,
 *   the markdown-pages plugin expands the active track's filesystem
 *   children but leaves the other tracks collapsed). A builder that
 *   sees the URL can emit different shapes for different requests
 *   without the registry having to expose a "current request" hook.
 * - The registry stays simple: no event system, no priorities beyond
 *   the explicit `position` field.
 */
final class FrontendNavRegistry
{
    /** @var list<callable(UrlSegments): array<int, NavItem>> */
    private array $builders = [];

    /**
     * @param callable(UrlSegments): array<int, NavItem> $builder
     */
    public function contribute(callable $builder): void
    {
        $this->builders[] = $builder;
    }

    /**
     * Build the merged, sorted top-level NavItem list for the given
     * request URL. Non-NavItem entries returned by a builder are
     * silently dropped.
     *
     * @return list<NavItem>
     */
    public function collect(UrlSegments $url): array
    {
        $entries = [];
        $regIndex = 0;
        foreach ($this->builders as $builder) {
            $items = $builder($url);
            if (! is_iterable($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! $item instanceof NavItem) {
                    continue;
                }
                $entries[] = [$item->position, $regIndex++, $item];
            }
        }
        usort($entries, static function (array $a, array $b): int {
            if ($a[0] !== $b[0]) {
                return $a[0] <=> $b[0];
            }
            return $a[1] <=> $b[1];
        });
        return array_map(static fn (array $e): NavItem => $e[2], $entries);
    }

    /**
     * Number of registered contributors. Used by the PluginsModule
     * editor surface to report per-plugin counts.
     */
    public function contributorCount(): int
    {
        return count($this->builders);
    }
}
