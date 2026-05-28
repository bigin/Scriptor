<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events\Editor;

use Scriptor\Boot\Frontend\Page;

/**
 * Dispatched once by {@see Scriptor\Boot\Editor\Pages\PagesModule} at
 * the top of the edit-form render. Listeners append HTML into one of
 * the named slots; PagesModule prints each slot's buffer right after
 * the matching core field, so plugin fields can land anywhere in the
 * form (under Content, under Images, at the very end, …) without
 * forking PagesModule.
 *
 * The default slot for {@see appendHtml()} when no slot is given is
 * {@see SLOT_END}, which renders just after the Published checkbox
 * and before the form's hidden action/csrf inputs. Listeners written
 * against earlier revisions of the event keep working unchanged.
 *
 * Companion event {@see PageSaving} fires when the form is submitted
 * so the same plugin can persist the posted values.
 *
 * Conventional listener body:
 *
 *     function (PageFormRendering $event): void {
 *         $current = $event->page?->meta_title ?? '';
 *         $event->appendHtml(
 *             '<div class="form-control"><label for="meta-title">Meta title</label>'
 *             . '<input id="meta-title" name="meta_title" type="text" value="'
 *             . htmlspecialchars($current, ENT_QUOTES) . '"></div>',
 *             PageFormRendering::SLOT_AFTER_CONTENT,
 *         );
 *     }
 */
final class PageFormRendering
{
    public const SLOT_AFTER_NAME       = 'after-name';
    public const SLOT_AFTER_MENU_TITLE = 'after-menu-title';
    public const SLOT_AFTER_SLUG       = 'after-slug';
    public const SLOT_AFTER_CONTENT    = 'after-content';
    public const SLOT_AFTER_IMAGES     = 'after-images';
    public const SLOT_AFTER_PARENT     = 'after-parent';
    public const SLOT_AFTER_TEMPLATE   = 'after-template';
    public const SLOT_AFTER_POSITION   = 'after-position';
    public const SLOT_AFTER_PUBLISHED  = 'after-published';

    /**
     * End of the field list, just before the hidden action/csrf
     * inputs and the Save button. Default slot for legacy
     * `appendHtml($html)` calls that omit the slot argument.
     */
    public const SLOT_END = 'end';

    /** @var array<string, string> */
    private array $slots = [];

    public function __construct(
        /** Page being edited; null for the new-page flow. */
        public readonly ?Page $page,
        /** id of the iManager category the form is editing. */
        public readonly int $categoryId,
    ) {}

    /**
     * Append markup into a named slot. The buffer for that slot is
     * printed verbatim after the matching core field. Listener owns
     * its HTML escaping; the buffer is not re-encoded.
     */
    public function appendHtml(string $html, string $slot = self::SLOT_END): void
    {
        $this->slots[$slot] = ($this->slots[$slot] ?? '') . $html;
    }

    /**
     * Return everything appended into a slot (empty string when no
     * listener targeted it). Called by PagesModule once per slot,
     * never by listeners directly.
     */
    public function htmlFor(string $slot): string
    {
        return $this->slots[$slot] ?? '';
    }
}
