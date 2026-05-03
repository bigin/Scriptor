<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Install;

use Scriptor\Boot\Editor\Editor;

/**
 * Install module — list site/modules/* candidates, install/uninstall
 * each by patching `data/settings/custom.scriptor-config.php` and
 * keeping rolling backups in `data/backups/configs/`.
 *
 * Compared to the legacy 618-line version this port:
 *   - drops the `brick/varexporter` dependency in favour of PHP's
 *     `var_export()` (the legacy code only ever emitted plain config
 *     arrays, no closures);
 *   - skips the install/uninstall lifecycle callbacks on the loaded
 *     module (legacy `Module::install/uninstall`) — the new module
 *     interface lands post-Phase-17;
 *   - skips the hook-mapping bridge (Plan §14e brings the proper
 *     PSR-14 dispatcher; until then we don't touch `config['hooks']`).
 *
 * The legacy `moduleInfo()` static contract is preserved: every
 * module class under `site/modules/<Name>/<Name>.php` is expected to
 * expose `public static function moduleInfo(): array`. Modules that
 * don't are skipped silently.
 */
final class InstallModule
{
    private const DEFAULT_CUSTOM_CONFIG = [
        'modules' => [],
        'hooks'   => [],
    ];

    /** @var list<string> Keys merged into the persisted module entry. */
    private const ENTRY_KEYS = [
        'name',
        'position',
        'menu',
        'display_type',
        'icon',
        'active',
        'auth',
        'autoinit',
        'path',
        'class',
        'version',
        'description',
        'author',
        'author_website',
        'author_email_address',
    ];

    private string $customConfigPath;
    private string $backupDir;

    public function __construct(
        private readonly Editor $editor,
        private readonly string $scriptorRoot,
    ) {
        $this->customConfigPath = $this->scriptorRoot . '/data/settings/custom.scriptor-config.php';
        $this->backupDir        = $this->scriptorRoot . '/data/backups/configs';
    }

    public function execute(): void
    {
        $sub = $this->editor->urlSegments->get(1);
        $action = $this->editor->input->getString('action');

        if ($sub !== null && \in_array($action, ['install', 'uninstall'], true)) {
            $this->writeAction($sub, $action);
            return;
        }

        $this->renderList();
    }

    private function writeAction(string $moduleName, string $action): void
    {
        if (! $this->csrfPasses($this->editor->input->getString('tokenName'), $this->editor->input->getString('tokenValue'))) {
            $this->editor->flashMsg('error', $this->t('error_csrf_token_mismatch'));
            $this->redirect($this->editor->siteUrl . '/install/');
        }

        $module = $this->findModule($moduleName);
        if ($module === null) {
            $this->editor->flashMsg('error', $this->t('install_module_name_not_found'));
            $this->redirect($this->editor->siteUrl . '/install/');
        }

        $config = $this->getCustomConfig();
        $this->createBackup();

        if ($action === 'install') {
            $config['modules'][$moduleName] = $this->prepareEntry($module);
            $this->writeConfig($config);
            $this->editor->flashMsg('success', $this->fillVars(
                $this->t('install_backup_message'),
                ['module_name' => $moduleName, 'custom_config_path' => $this->customConfigPath],
            ));
        } else {
            unset($config['modules'][$moduleName]);
            $this->writeConfig($config);
            $this->editor->flashMsg('success', $this->fillVars(
                $this->t('uninstall_module_successful'),
                ['module_name' => $moduleName],
            ));
        }

        $this->redirect($this->editor->siteUrl . '/install/');
    }

    private function renderList(): void
    {
        $modules = $this->getModuleList();
        $config = $this->getCustomConfig();
        $token = $this->editor->csrf->token('install');

        $this->editor->pageTitle = 'Module Installation - Scriptor';
        $this->editor->breadcrumbs = sprintf(
            '<li><span>%s</span></li>',
            htmlspecialchars($this->t('install_menu') ?: 'Modules', \ENT_QUOTES),
        );

        $i = static fn(string $s): string => htmlspecialchars($s, \ENT_QUOTES);
        $rows = '';
        foreach ($modules as $module) {
            $name = (string) ($module['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $installed = isset($config['modules'][$name]);
            $cls = $installed ? 'active' : 'inactive';
            $href = $this->editor->siteUrl . '/install/' . rawurlencode($name)
                . '?action=' . ($installed ? 'uninstall' : 'install')
                . '&tokenName=install&tokenValue=' . rawurlencode($token);
            $btn = $installed
                ? '<a href="' . $i($href) . '" class="remove button-badge"><i class="gg-export"></i> '
                    . $i($this->t('uninstall_button') ?: 'Uninstall') . '</a>'
                : '<a href="' . $i($href) . '" class="remove button-badge"><i class="gg-import"></i> '
                    . $i($this->t('install_button') ?: 'Install') . '</a>';
            $rows .= sprintf(
                '<tr><td class="%s">%s (%s)</td><td class="%1$s">%s</td><td class="%1$s">%s</td></tr>',
                $cls,
                $i($name),
                $i((string) ($module['version'] ?? '')),
                $i((string) ($module['description'] ?? '')),
                $btn,
            );
        }

        $body = $rows !== ''
            ? $this->t('install_info_text')
            : $this->t('install_no_modules_found');

        $this->editor->pageContent =
            '<h1>' . $i($this->t('install_module_list_header') ?: 'Module Manager') . '</h1>'
            . '<p>' . $body . '</p>'
            . '<table class="item-table"><thead><tr>'
            . '<th><b>' . $i($this->t('install_table_column_name') ?: 'Name') . '</b></th>'
            . '<th><b>' . $i($this->t('install_table_column_description') ?: 'Description') . '</b></th>'
            . '<th class="text-center"><b>' . $i($this->t('install_table_column_action') ?: 'Action') . '</b></th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getModuleList(): array
    {
        $base = $this->scriptorRoot . '/site/modules';
        if (! is_dir($base)) {
            return [];
        }
        $entries = [];
        $dirs = glob($base . '/*', \GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $name = basename($dir);
            $file = $dir . '/' . $name . '.php';
            if (! is_file($file)) {
                continue;
            }
            $info = self::loadModuleInfo($file);
            if ($info === null) {
                continue;
            }
            $entries[] = $info;
        }
        usort(
            $entries,
            static fn(array $a, array $b): int => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)),
        );
        return $entries;
    }

    private function findModule(string $name): ?array
    {
        foreach ($this->getModuleList() as $module) {
            if (($module['name'] ?? null) === $name) {
                return $module;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>
     */
    private function prepareEntry(array $module): array
    {
        $entry = [];
        foreach (self::ENTRY_KEYS as $key) {
            if (\array_key_exists($key, $module)) {
                $entry[$key] = $module[$key];
            }
        }
        return $entry;
    }

    /**
     * Loads `<Name>.php`, looks up the namespaced class, calls its
     * static `moduleInfo()` method. Returns null when the file doesn't
     * declare the expected class or `moduleInfo()`.
     *
     * @return array<string, mixed>|null
     */
    private static function loadModuleInfo(string $file): ?array
    {
        $namespace = self::extractNamespace($file);
        $class = basename($file, '.php');
        $fqn = $namespace !== '' ? $namespace . '\\' . $class : $class;

        require_once $file;

        if (! class_exists($fqn) || ! method_exists($fqn, 'moduleInfo')) {
            return null;
        }
        $info = $fqn::moduleInfo();
        if (! \is_array($info)) {
            return null;
        }
        // Default `name` and `class` so callers don't need to repeat them.
        $info['name']  = (string) ($info['name'] ?? $class);
        $info['class'] = (string) ($info['class'] ?? $fqn);
        return $info;
    }

    private static function extractNamespace(string $file): string
    {
        $src = (string) @file_get_contents($file);
        if (preg_match('/^\s*namespace\s+([^;{\s]+)\s*[;{]/m', $src, $m) === 1) {
            return trim($m[1], '\\');
        }
        return '';
    }

    /**
     * @return array{modules: array<string, mixed>, hooks: array<string, mixed>}
     */
    private function getCustomConfig(): array
    {
        if (! is_file($this->customConfigPath)) {
            return self::DEFAULT_CUSTOM_CONFIG;
        }
        $loaded = include $this->customConfigPath;
        if (! \is_array($loaded)) {
            return self::DEFAULT_CUSTOM_CONFIG;
        }
        /** @var array{modules?: array<string, mixed>, hooks?: array<string, mixed>} $loaded */
        return [
            'modules' => $loaded['modules'] ?? [],
            'hooks'   => $loaded['hooks']   ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeConfig(array $config): void
    {
        $body = "<?php\n\n"
            . "// Generated by Scriptor's Module Manager. Edit with care:\n"
            . "// any subsequent install/uninstall rewrites this file from\n"
            . "// scratch (a backup is kept under data/backups/configs/).\n\n"
            . 'return ' . var_export($config, true) . ";\n";
        @file_put_contents($this->customConfigPath, $body);
    }

    private function createBackup(): bool
    {
        if (! is_file($this->customConfigPath)) {
            return false;
        }
        $maxFiles = (int) ($this->editor->config['maxConfigBackupFiles'] ?? 0);
        if ($maxFiles === 0) {
            return false;
        }
        if (! is_dir($this->backupDir) && ! @mkdir($this->backupDir, 0o755, true) && ! is_dir($this->backupDir)) {
            return false;
        }
        $existing = glob($this->backupDir . '/*_custom.scriptor-config.php.backup') ?: [];
        if (\count($existing) >= $maxFiles) {
            usort($existing, static fn(string $a, string $b): int => filemtime($a) <=> filemtime($b));
            foreach (\array_slice($existing, 0, \count($existing) - ($maxFiles - 1)) as $stale) {
                @unlink($stale);
            }
        }
        return @copy(
            $this->customConfigPath,
            $this->backupDir . '/' . time() . '_custom.scriptor-config.php.backup',
        );
    }

    private function csrfPasses(string $name, string $value): bool
    {
        if (! ($this->editor->config['protectCSRF'] ?? true)) {
            return true;
        }
        if ($name === '' || $value === '') {
            return false;
        }
        return $this->editor->csrf->validate($name, $value);
    }

    private function t(string $key): string
    {
        return $this->editor->i18n[$key] ?? '';
    }

    /**
     * @param array<string, string> $vars
     */
    private function fillVars(string $template, array $vars): string
    {
        if ($template === '') {
            return '';
        }
        foreach ($vars as $name => $value) {
            $template = str_replace('[[' . $name . ']]', $value, $template);
        }
        return $template;
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}
