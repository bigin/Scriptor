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
 * Editor URL router — dispatches to the right module.
 *
 * Modules:
 *   - auth      login / logout (CSRF + bcrypt password verification)
 *   - pages     page list + edit (FilePond uploads, Markdown preview)
 *   - profile   logged-in user edits their own record
 *   - settings  read-only "edit data/settings/*.php manually" page
 *   - install   discover / install / uninstall site/modules/* plugins
 *   - api       JSON endpoints (/editor/api/upload — POST/PATCH/DELETE)
 *
 * Auth gating:
 *   - `auth/*` is always reachable (anonymous login form);
 *   - `api/*` returns 401 JSON for anonymous callers;
 *   - everything else 302's to `/editor/auth/` until logged in.
 *
 * Unknown module slugs return a 404 page through {@see renderUnknownModule()}.
 */
final class EditorRouter
{
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
            . '<p>Pick a module from the sidebar to start editing.</p>';
    }

    private function renderUnknownModule(string $module): void
    {
        http_response_code(404);
        $this->editor->pageTitle = 'Module not found';
        $this->editor->pageContent =
            '<h1>404</h1>'
            . '<p>Module <code>' . htmlspecialchars($module, \ENT_QUOTES) . '</code> is not available.</p>';
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}
