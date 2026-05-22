<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor;

use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessor;
use Imanager\Storage\FileRepository;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use League\Container\Container;
use Scriptor\Boot\Editor\Api\UploadEndpoint;

/**
 * Editor URL router. The first segment selects which module handles
 * the request. Modules live behind {@see ModuleRegistry}: both first-
 * party surfaces (auth, pages, profile, settings, install) and
 * plugin-contributed ones are registered the same way, so this
 * dispatcher has one code path instead of an if-ladder.
 *
 * Auth gating is still hard-coded here because it crosses every
 * module rather than living inside one. The flow is:
 *
 *   /auth(/...)       always reachable (the login form needs to be)
 *   /api/...          401 JSON when not logged in; auth otherwise
 *   /<anything-else>  302 to /auth/ until logged in
 *
 * Once auth has passed, the dispatcher looks up the first URL segment
 * in the registry and instantiates the module via its factory. The
 * factory receives the DI container plus this request's Editor, which
 * is enough to resolve every per-request dependency (the same closures
 * that used to live in dispatch*() helpers below).
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
            $this->dispatchModule('auth');
            return;
        }

        if (! $this->editor->isLoggedIn()) {
            if ($first === 'api') {
                $this->jsonError(401, 'Authentication required');
            }
            $this->editor->redirect($this->editor->siteUrl . '/auth/');
        }

        if ($first === 'api') {
            $this->dispatchApi();
            return;
        }

        if ($first === null) {
            $this->renderDashboard();
            return;
        }

        if (! $this->moduleRegistry()->has($first)) {
            $this->renderUnknownModule($first);
            return;
        }

        $this->dispatchModule($first);
    }

    private function dispatchModule(string $slug): void
    {
        $module = $this->moduleRegistry()->create($slug, $this->container, $this->editor);
        $module->execute();
    }

    private function moduleRegistry(): ModuleRegistry
    {
        /** @var ModuleRegistry $registry */
        $registry = $this->container->get(ModuleRegistry::class);
        return $registry;
    }

    /**
     * JSON sub-tree under /editor/api/*. The only endpoint today is
     * upload; adding more would justify an ApiEndpointRegistry, but
     * for one entry inline dispatch is clearer than over-design.
     */
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

}
