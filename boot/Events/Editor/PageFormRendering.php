<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events\Editor;

use Scriptor\Boot\Frontend\Page;

/**
 * Dispatched by {@see Scriptor\Boot\Editor\Pages\PagesModule} after the
 * built-in form fields are rendered and before the form's hidden
 * inputs + Save button so plugins can add their own fields (SEO meta,
 * scheduling, OG tags, custom taxonomies, …) without forking
 * PagesModule.
 *
 * Listeners append their `<div class="form-control">…</div>` markup
 * via {@see appendHtml()}. The accumulated buffer is consumed by
 * PagesModule and emitted verbatim into the form. The listener is
 * responsible for its own escaping; the buffer is not re-encoded.
 *
 * Companion event {@see PageSaving} fires when the form is submitted
 * so the same plugin can persist its values.
 *
 * Conventional listener body:
 *
 *     function (PageFormRendering $event): void {
 *         $current = $event->page?->meta_title ?? '';
 *         $event->appendHtml(
 *             '<div class="form-control"><label for="meta-title">Meta title</label>'
 *             . '<input id="meta-title" name="meta_title" type="text" value="'
 *             . htmlspecialchars($current, ENT_QUOTES) . '"></div>'
 *         );
 *     }
 */
final class PageFormRendering
{
    private string $extraHtml = '';

    public function __construct(
        /** Page being edited; null for the new-page flow. */
        public readonly ?Page $page,
        /** id of the iManager category the form is editing. */
        public readonly int $categoryId,
    ) {}

    /** Append markup to the buffer flushed into the form. */
    public function appendHtml(string $html): void
    {
        $this->extraHtml .= $html;
    }

    /** All collected extra markup. PagesModule prints this verbatim. */
    public function html(): string
    {
        return $this->extraHtml;
    }
}
