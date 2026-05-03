<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor;

use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessor;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\FileRepository;
use Imanager\Storage\ItemRepository;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use League\Container\Container;
use Scriptor\Boot\Editor\Api\UploadEndpoint;
use Scriptor\Boot\Editor\Auth\AuthModule;
use Scriptor\Boot\Editor\Auth\LoginAttempts;
use Scriptor\Boot\Editor\Install\InstallModule;
use Scriptor\Boot\Editor\Pages\PagesModule;
use Scriptor\Boot\Editor\Profile\ProfileModule;
use Scriptor\Boot\Editor\Settings\SettingsModule;
use Scriptor\Boot\Frontend\PageRepository;

/**
 * Phase 14c-1 router: dispatches the editor URL to the right module.
 *
 * Active modules:
 *   - auth (login/logout) — built on iManager 2.0 Csrf + Request +
 *     SessionStore + PasswordFieldType-hashed credentials.
 *
 * Anything else (pages/users/settings/install/profile) returns a
 * placeholder page until its sub-phase ships. The placeholder is
 * intentional: the editor stays reachable end-to-end on this branch
 * for smoke-testing the auth flow without 1.x fallbacks.
 *
 * Auth gating:
 *   - the auth module itself is always reachable (anonymous login form);
 *   - everything else requires `$editor->isLoggedIn()` and 302's to
 *     /editor/auth/ otherwise.
 */
final class EditorRouter
{
    /** @var array<string, string> Module slug → "phase pending" placeholder map. */
    private const PLACEHOLDER_MODULES = [];

    public function __construct(
        private readonly Editor $editor,
        private readonly Container $container,
    ) {}

    public function execute(): void
    {
        $first = $this->editor->urlSegments->first();

        if ($first === 'auth' || ($first === null && ! $this->editor->isLoggedIn())) {
            $this->dispatchAuth();
            return;
        }

        if (! $this->editor->isLoggedIn()) {
            // JSON endpoints get a 401 instead of a 302 so XHR callers
            // (FilePond, future SPA bits) can react to it cleanly.
            if ($first === 'api') {
                $this->jsonError(401, 'Authentication required');
            }
            $this->redirect($this->editor->siteUrl . '/auth/');
        }

        if ($first === 'api') {
            $this->dispatchApi();
            return;
        }

        if ($first === null) {
            $this->renderDashboard();
            return;
        }

        if ($first === 'pages') {
            $this->dispatchPages();
            return;
        }

        if ($first === 'profile') {
            $this->dispatchProfile();
            return;
        }

        if ($first === 'settings') {
            (new SettingsModule($this->editor))->execute();
            return;
        }

        if ($first === 'install') {
            (new InstallModule($this->editor, dirname(__DIR__, 2)))->execute();
            return;
        }

        if (isset(self::PLACEHOLDER_MODULES[$first])) {
            $this->renderPlaceholder($first, self::PLACEHOLDER_MODULES[$first]);
            return;
        }

        $this->renderUnknownModule($first);
    }

    private function dispatchPages(): void
    {
        $module = new PagesModule(
            $this->editor,
            new PageRepository(
                $this->container->get(CategoryRepository::class),
                $this->container->get(ItemRepository::class),
            ),
            $this->container->get(FieldRepository::class),
            $this->container->get(FileRepository::class),
        );
        $module->execute();
    }

    private function dispatchProfile(): void
    {
        $module = new ProfileModule(
            $this->editor,
            new UserRepository(
                $this->container->get(CategoryRepository::class),
                $this->container->get(ItemRepository::class),
            ),
        );
        $module->execute();
    }

    private function dispatchApi(): void
    {
        $resource = $this->editor->urlSegments->get(1);
        if ($resource !== 'upload') {
            $this->jsonError(404, 'Unknown api resource');
        }
        $endpoint = new UploadEndpoint(
            $this->editor,
            $this->container->get(FileRepository::class),
            $this->container->get(FileStorage::class),
            $this->container->get(ImanagerSanitizer::class),
            $this->container->get(ImageProcessor::class),
        );
        $endpoint->handle($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    private function jsonError(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message]);
        exit;
    }

    private function dispatchAuth(): void
    {
        $auth = new AuthModule(
            $this->editor,
            new UserRepository(
                $this->container->get(CategoryRepository::class),
                $this->container->get(ItemRepository::class),
            ),
            new LoginAttempts(
                $this->editor->session,
                maxAttempts: (int) ($this->editor->config['maxFailedAccessAttempts'] ?? 5),
                lockoutMinutes: (int) ($this->editor->config['accessLockoutDuration'] ?? 5),
            ),
        );
        $auth->execute();
    }

    private function renderDashboard(): void
    {
        $this->editor->pageTitle = 'Dashboard - Scriptor';
        $this->editor->pageContent =
            '<h1>' . htmlspecialchars($this->editor->i18n['dashboard_menu'] ?? 'Dashboard', \ENT_QUOTES) . '</h1>'
            . '<p>iManager 2.0 editor — pick a module from the sidebar.</p>'
            . $this->placeholderModuleList();
    }

    private function renderPlaceholder(string $module, string $phase): void
    {
        $this->editor->pageTitle = ucfirst($module) . ' (coming soon) - Scriptor';
        $this->editor->pageContent =
            '<h1>' . htmlspecialchars(ucfirst($module), \ENT_QUOTES) . '</h1>'
            . '<p>The <strong>' . htmlspecialchars($module, \ENT_QUOTES) . '</strong> module '
            . 'will be reattached in phase <code>' . htmlspecialchars($phase, \ENT_QUOTES) . '</code>.</p>';
    }

    private function renderUnknownModule(string $module): void
    {
        http_response_code(404);
        $this->editor->pageTitle = 'Module not found';
        $this->editor->pageContent =
            '<h1>404</h1>'
            . '<p>Module <code>' . htmlspecialchars($module, \ENT_QUOTES) . '</code> is not available.</p>';
    }

    private function placeholderModuleList(): string
    {
        if (self::PLACEHOLDER_MODULES === []) {
            return '';
        }
        $items = '';
        foreach (self::PLACEHOLDER_MODULES as $slug => $phase) {
            $items .= \sprintf(
                '<li><code>%s</code> — %s</li>',
                htmlspecialchars($slug, \ENT_QUOTES),
                htmlspecialchars($phase, \ENT_QUOTES),
            );
        }
        return '<h2>Pending sub-phases</h2><ul>' . $items . '</ul>';
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}
