<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin\CorePlugins;

use Imanager\Http\UrlSegments;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use Scriptor\Boot\Events\Frontend\PageResolving;
use Scriptor\Boot\Frontend\Page;
use Scriptor\Boot\Frontend\PageRepository;
use Scriptor\Boot\Plugin\Plugin;
use Scriptor\Boot\Plugin\PluginContext;

/**
 * Built-in resolver that maps URL segments to a {@see Page} via the
 * iManager-backed slug table. Before the Plugin API existed, this
 * logic sat inline in {@see Scriptor\Boot\Frontend\Site::execute()};
 * extracting it into a plugin makes the dispatch pipeline uniform:
 * core resolution and third-party resolution travel the same path.
 *
 * Resolution rules (preserved verbatim from the old Site::execute):
 *
 * - Empty URL falls back to the home page (id 1).
 * - Otherwise the last segment is sanitised to a slug and looked up.
 * - The home page is reachable both via the empty URL and via its
 *   own slug; everything else must match its full parent chain or
 *   the resolution is rejected (the request will fall through to
 *   {@see Site::throw404()}).
 * - Inactive pages do not resolve.
 *
 * The plugin self-checks the resolution slot at the start of its
 * handler: if any earlier listener already filled it, this plugin
 * does nothing. That is the "first writer wins" convention every
 * PageResolving listener follows.
 */
final class DbPagesResolverPlugin implements Plugin
{
    public function name(): string
    {
        return 'core/db-pages-resolver';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(PluginContext $context): void
    {
        $container = $context->container();
        $context->subscribe(
            PageResolving::class,
            static function (PageResolving $event) use ($container): void {
                if ($event->resolution !== null) {
                    return;
                }
                $pages     = $container->get(PageRepository::class);
                $sanitizer = $container->get(ImanagerSanitizer::class);
                $resolved  = self::resolve($event->urlSegments, $pages, $sanitizer);
                if ($resolved !== null) {
                    $event->resolution = $resolved;
                }
            },
        );
    }

    private static function resolve(
        UrlSegments $segments,
        PageRepository $pages,
        ImanagerSanitizer $sanitizer,
    ): ?Page {
        if ($segments->isEmpty()) {
            $home = $pages->findHome();
            return ($home !== null && $home->active()) ? $home : null;
        }

        $last = $segments->last();
        if ($last === null) {
            $home = $pages->findHome();
            return ($home !== null && $home->active()) ? $home : null;
        }

        $slug = $sanitizer->slug($last);
        $page = $pages->findBySlug($slug);
        if ($page === null || ! $page->active()) {
            return null;
        }

        // Every page must match its canonical parent-chain URL so
        // a request like `/wrong-parent/articles/` does not silently
        // render the matching child page elsewhere in the tree.
        // The home page (empty slug) has its own URL `/` which is
        // handled by the `$segments->isEmpty()` branch above; a
        // slug-routed request can never reach it because no path
        // segment ever equals the empty string.
        $expected = self::buildPageUrl($page, $pages);
        $actual   = $segments->path(trailingSlash: true);
        if ($actual !== $expected) {
            return null;
        }

        return $page;
    }

    /**
     * @param array<int, true> $visited
     */
    private static function buildPageUrl(Page $page, PageRepository $pages, array $visited = []): string
    {
        $id = $page->id() ?? 0;
        if (isset($visited[$id])) {
            return $page->slug !== '' ? $page->slug . '/' : '';
        }
        $visited[$id] = true;

        $url = '';
        if ($page->parent !== 0 && $page->parent !== $id) {
            $parent = $pages->find($page->parent);
            if ($parent !== null) {
                $url .= self::buildPageUrl($parent, $pages, $visited);
            }
        }
        if ($page->slug !== '') {
            $url .= $page->slug . '/';
        }
        return $url;
    }
}
