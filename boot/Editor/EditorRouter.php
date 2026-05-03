<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor;

use League\Container\Container;
use Scriptor\Boot\Editor\Auth\AuthModule;
use Scriptor\Boot\Editor\Auth\LoginAttempts;

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
    private const PLACEHOLDER_MODULES = [
        'pages'    => '14c-2',
        'profile'  => '14c-6',
        'settings' => '14c-4',
        'install'  => '14c-5',
        'users'    => '14c-3',
    ];

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
            $this->redirect($this->editor->siteUrl . '/auth/');
        }

        if ($first === null) {
            $this->renderDashboard();
            return;
        }

        if (isset(self::PLACEHOLDER_MODULES[$first])) {
            $this->renderPlaceholder($first, self::PLACEHOLDER_MODULES[$first]);
            return;
        }

        $this->renderUnknownModule($first);
    }

    private function dispatchAuth(): void
    {
        $auth = new AuthModule(
            $this->editor,
            new UserRepository(
                $this->container->get(\Imanager\Storage\CategoryRepository::class),
                $this->container->get(\Imanager\Storage\ItemRepository::class),
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
            . '<p>iManager 2.0 editor — Phase 14c-1 (auth) is live. '
            . 'Other admin modules come back online with their own sub-phase.</p>'
            . $this->placeholderModuleList();
    }

    private function renderPlaceholder(string $module, string $phase): void
    {
        $this->editor->pageTitle = ucfirst($module) . ' (coming soon) - Scriptor';
        $this->editor->pageContent =
            '<h1>' . htmlspecialchars(ucfirst($module), \ENT_QUOTES) . '</h1>'
            . '<p>The <strong>' . htmlspecialchars($module, \ENT_QUOTES) . '</strong> module '
            . 'will be reattached in phase <code>' . htmlspecialchars($phase, \ENT_QUOTES) . '</code>.</p>'
            . $this->placeholderModuleList();
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
        $items = '';
        foreach (self::PLACEHOLDER_MODULES as $slug => $phase) {
            $items .= \sprintf(
                '<li><code>%s</code> — %s</li>',
                htmlspecialchars($slug, \ENT_QUOTES),
                htmlspecialchars($phase, \ENT_QUOTES),
            );
        }
        return '<h2>Phase 14c roadmap</h2><ul>' . $items . '</ul>';
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}
