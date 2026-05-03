<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Settings;

use Scriptor\Boot\Editor\Editor;

/**
 * Settings module — informational page that points the admin at the
 * legacy `data/settings/scriptor-config.php` file (and the
 * `custom.scriptor-config.php` override). The same shape the 1.x
 * module shipped: no form, no writes, just rendered i18n strings.
 *
 * A future iManager 2.0 settings UI (post-Phase-17) would replace
 * this with an in-place editor; for Phase 14 we keep parity.
 */
final class SettingsModule
{
    public function __construct(private readonly Editor $editor) {}

    public function execute(): void
    {
        $this->editor->pageTitle = 'Settings - Scriptor';
        $this->editor->breadcrumbs = sprintf(
            '<li><span>%s</span></li>',
            htmlspecialchars($this->editor->i18n['settings_menu'] ?? 'Settings', \ENT_QUOTES),
        );

        $header = $this->editor->i18n['settings_page_header'] ?? 'System settings';
        // The legacy `settings_page_text` already contains intentional HTML
        // (a couple of <mark> tags and line breaks) — render it verbatim
        // rather than escaping it.
        $body = $this->editor->i18n['settings_page_text'] ?? '';

        $this->editor->pageContent =
            '<h1>' . htmlspecialchars($header, \ENT_QUOTES) . '</h1>'
            . '<p>' . $body . '</p>';
    }
}
