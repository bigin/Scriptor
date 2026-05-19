<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Plugins;

use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\Module;
use Scriptor\Boot\Plugin\PluginManager;

/**
 * Read-only browser for installed Scriptor plugins.
 *
 * Replaces the legacy `InstallModule` which scanned `site/modules/*`
 * for the pre-Plugin-API extension mechanism. Now the source of truth
 * is the {@see PluginManager}: this module lists every plugin that
 * was discovered + booted (core plugins shipped inside Scriptor itself
 * plus Composer packages with `type: scriptor-plugin`), and the
 * surfaces each one contributes through the Plugin API.
 *
 * No write actions here. Plugins are managed through Composer
 * (`composer require <vendor>/<plugin>`) and disabled by listing their
 * FQCN in `$config['plugins']['disabled']`. The module exists so
 * operators can see what is loaded without scrolling through PHP code.
 */
final class PluginsModule implements Module
{
    public function __construct(
        private readonly Editor $editor,
        private readonly PluginManager $pluginManager,
    ) {}

    public function execute(): void
    {
        $this->editor->pageTitle   = 'Plugins - Scriptor';
        $this->editor->breadcrumbs = '<li><span>'
            . htmlspecialchars($this->t('plugins_menu'), \ENT_QUOTES)
            . '</span></li>';

        $bootedRows = $this->renderBootedRows();
        $disabled   = $this->renderDisabledSection();

        $this->editor->pageContent =
            '<h1>' . htmlspecialchars($this->t('plugins_menu'), \ENT_QUOTES) . '</h1>'
            . '<p>Read-only view of installed Scriptor plugins. '
            . 'Install with <code>composer require</code>; disable by '
            . 'adding the plugin FQCN to '
            . '<code>$config[\'plugins\'][\'disabled\']</code> in '
            . '<code>data/settings/custom.scriptor-config.php</code>.</p>'
            . '<table class="plugins-list"><thead><tr>'
            . '<th>Plugin</th><th>Version</th><th>Events</th>'
            . '<th>Editor modules</th><th>Menu items</th>'
            . '</tr></thead><tbody>' . $bootedRows . '</tbody></table>'
            . $disabled;
    }

    private function renderBootedRows(): string
    {
        $booted = $this->pluginManager->bootedPlugins();
        if ($booted === []) {
            return '<tr><td colspan="5"><em>No plugins booted.</em></td></tr>';
        }
        $rows = '';
        foreach ($booted as $plugin) {
            $name = $plugin->name();
            $regs = $this->pluginManager->registrationsFor($name) ?? [
                'events' => [], 'modules' => [], 'menuItems' => [], 'navBuilders' => 0,
            ];
            // Prefer the Composer manifest version over Plugin::version()
            // for Composer-installed plugins. The manifest matches the
            // git tag composer resolved; the hardcoded version() string
            // is a footgun when a plugin author forgets to bump it in
            // the release commit. Core plugins (no manifest) keep
            // their own version() string.
            $manifest = $this->pluginManager->manifestFor($name);
            $version  = $manifest !== null ? $manifest->packageVersion : $plugin->version();
            $rows .= sprintf(
                '<tr>'
                . '<td><strong>%s</strong></td>'
                . '<td><code>%s</code></td>'
                . '<td>%s</td>'
                . '<td>%s</td>'
                . '<td>%s</td>'
                . '</tr>',
                htmlspecialchars($name, \ENT_QUOTES),
                htmlspecialchars($version, \ENT_QUOTES),
                $this->renderEventList($regs['events']),
                $this->renderModuleList($regs['modules']),
                $this->renderMenuList($regs['menuItems']),
            );
        }
        return $rows;
    }

    /**
     * @param list<string> $events
     */
    private function renderEventList(array $events): string
    {
        if ($events === []) {
            return '<em>none</em>';
        }
        $items = '';
        foreach ($events as $eventClass) {
            $short = ltrim(strrchr($eventClass, '\\') ?: $eventClass, '\\');
            $items .= '<li><code title="' . htmlspecialchars($eventClass, \ENT_QUOTES) . '">'
                . htmlspecialchars($short, \ENT_QUOTES) . '</code></li>';
        }
        return '<ul class="plugins-cell-list">' . $items . '</ul>';
    }

    /**
     * @param list<string> $modules
     */
    private function renderModuleList(array $modules): string
    {
        if ($modules === []) {
            return '<em>none</em>';
        }
        $items = '';
        foreach ($modules as $slug) {
            $items .= '<li><code>/editor/' . htmlspecialchars($slug, \ENT_QUOTES) . '/</code></li>';
        }
        return '<ul class="plugins-cell-list">' . $items . '</ul>';
    }

    /**
     * @param list<\Scriptor\Boot\Editor\Menu\MenuItem> $items
     */
    private function renderMenuList(array $items): string
    {
        if ($items === []) {
            return '<em>none</em>';
        }
        $rendered = '';
        foreach ($items as $item) {
            $rendered .= sprintf(
                '<li><code>%s</code> &rarr; %s</li>',
                htmlspecialchars($item->displayType, \ENT_QUOTES),
                htmlspecialchars($item->label, \ENT_QUOTES),
            );
        }
        return '<ul class="plugins-cell-list">' . $rendered . '</ul>';
    }

    private function renderDisabledSection(): string
    {
        $disabled = (array) ($this->editor->config['plugins']['disabled'] ?? []);
        if ($disabled === []) {
            return '';
        }
        $items = '';
        foreach ($disabled as $fqcn) {
            $items .= '<li><code>' . htmlspecialchars((string) $fqcn, \ENT_QUOTES) . '</code></li>';
        }
        return '<h2>Disabled</h2>'
            . '<p>The following plugin FQCNs are listed under '
            . '<code>$config[\'plugins\'][\'disabled\']</code> and are '
            . 'skipped at boot:</p>'
            . '<ul>' . $items . '</ul>';
    }

    private function t(string $key): string
    {
        return (string) ($this->editor->i18n[$key] ?? $key);
    }
}
