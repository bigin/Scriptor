<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events\Editor;

use Imanager\Domain\Item;
use Imanager\Http\Request;

/**
 * Dispatched by {@see Scriptor\Boot\Editor\Pages\PagesModule::saveAction()}
 * just before the page item is written to storage. Listeners read
 * extra fields from the POST request and add them to {@see extraData},
 * which PagesModule merges into the Item's data bag before the save.
 *
 * This is the persistence companion of {@see PageFormRendering}.
 *
 * `extraData` keys override matching core keys when merged (last-write-
 * wins on the same key), so a listener can also override a built-in
 * value if it wants to; the typical case is adding new keys (meta_*,
 * og_*) the core doesn't know about.
 *
 * Conventional listener body:
 *
 *     function (PageSaving $event): void {
 *         $event->mergeData([
 *             'meta_title'       => trim($event->input->postString('meta_title')),
 *             'meta_description' => trim($event->input->postString('meta_description')),
 *         ]);
 *     }
 */
final class PageSaving
{
    /** @var array<string, mixed> */
    private array $extraData = [];

    public function __construct(
        /**
         * The about-to-be-saved item with the core fields already
         * populated from the form. Read-only; mutate via
         * {@see mergeData()} so the merge order is predictable.
         */
        public readonly Item $item,

        /** Per-request input bag. Listeners pull their own POST fields. */
        public readonly Request $input,
    ) {}

    /**
     * @param array<string, mixed> $extra
     */
    public function mergeData(array $extra): void
    {
        $this->extraData = [...$this->extraData, ...$extra];
    }

    /**
     * @return array<string, mixed>
     */
    public function extraData(): array
    {
        return $this->extraData;
    }
}
