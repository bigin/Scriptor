<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor;

/**
 * Contract every editor module satisfies.
 *
 * A module owns a top-level URL slug under `/editor/<slug>/...` and
 * runs from the {@see EditorRouter} once the auth gate (if any) has
 * passed. The router resolves the slug to a factory via
 * {@see ModuleRegistry}, instantiates the module per request, and
 * calls {@see execute()}.
 *
 * Render output lands on `$editor->pageContent`; the module never
 * echoes directly so the layout template (`editor/theme/template.php`)
 * stays in charge of head/header/footer/messages.
 */
interface Module
{
    public function execute(): void;
}
