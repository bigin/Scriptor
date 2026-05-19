<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events\Frontend;

use Scriptor\Boot\Frontend\Page;

/**
 * Dispatched by {@see Scriptor\Boot\Frontend\Site::renderContent()} so
 * plugins can substitute the rendered HTML for the resolved page.
 *
 * Listener slot is the mutable {@see $html} property:
 *
 * - If a listener sets `$event->html` to a non-null string, Site uses
 *   that as the rendered content and skips the default Parsedown
 *   sanitizer.
 * - If no listener sets it, the standard
 *   `$site->sanitizer->markdown($page->content)` pipeline runs.
 *
 * Cooperative convention: subsequent listeners should self-check by
 * leaving `$event->html` untouched if a prior listener already filled
 * it (first writer wins), unless they intentionally want to wrap the
 * earlier output.
 *
 * Typical use cases:
 *
 * - Markdown-pages plugin: detects pages whose Item data carries its
 *   marker and returns the CommonMark-rendered HTML it already
 *   computed during PageResolving.
 * - Shortcode plugin: lets the default Parsedown run, then this event
 *   could come back with post-processed output if we ever add the
 *   ability to peek at the in-flight content (today only the original
 *   page is exposed).
 */
final class ContentRendering
{
    public ?string $html = null;

    public function __construct(
        public readonly Page $page,
    ) {}
}
