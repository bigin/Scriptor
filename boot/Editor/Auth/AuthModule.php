<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Auth;

use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\Module;
use Scriptor\Boot\Editor\UserRepository;

/**
 * Auth module — login form + login/logout handlers on the iManager 2.0
 * Csrf + Request + SessionStore stack.
 *
 * Module contract for 14c-1: invoked by EditorRouter when the URL
 * resolves to `/editor/auth*`. Side effects (session writes, redirects)
 * happen inside `execute()`; render output lands on `$editor->pageContent`.
 *
 * Password verification goes through `password_verify()` against the
 * bcrypt hash that the migrator preserved in the user item's `password`
 * field (Phase 9 + iManager `PasswordFieldType`).
 */
final class AuthModule implements Module
{
    public function __construct(
        private readonly Editor $editor,
        private readonly UserRepository $users,
        private readonly LoginAttempts $attempts,
    ) {}

    public function execute(): void
    {
        $this->checkAction();

        if ($this->editor->isLoggedIn()) {
            $this->redirect($this->editor->siteUrl . '/');
        }
        $this->editor->pageTitle = 'Login - Scriptor';
        $this->editor->pageContent = $this->renderLoginForm();
    }

    private function checkAction(): void
    {
        if (! $this->editor->isLoggedIn()) {
            $this->loginAction();
            return;
        }
        if ($this->editor->urlSegments->get(1) === 'logout') {
            $this->logoutAction();
        }
    }

    private function loginAction(): void
    {
        if ($this->editor->input->postString('action') !== 'login') {
            return;
        }
        if ($this->editor->input->isMethod('POST') === false) {
            return;
        }

        $ip = self::clientIp();
        if (! $this->attempts->isAllowed($ip)) {
            $this->editor->addMsg('error', $this->t('error_max_login_attempts', [
                'count' => (string) $this->attempts->lockoutMinutes(),
            ]));
            return;
        }

        if ($this->csrfRequired() && ! $this->editor->csrf->validate(
            $this->editor->input->postString('tokenName'),
            $this->editor->input->postString('tokenValue'),
        )) {
            $this->editor->addMsg('error', $this->t('error_csrf_token_mismatch') ?: 'CSRF token invalid.');
            return;
        }

        $username = $this->editor->sanitizer->text($this->editor->input->postString('username'));
        $password = $this->editor->input->postString('password');
        if ($username === '' || $password === '') {
            $this->attempts->recordFailure($ip);
            $this->editor->addMsg('error', $this->t('error_login', [
                'count' => (string) $this->attempts->remainingAttempts($ip),
            ]));
            return;
        }

        $user = $this->users->findByName($username);
        $hash = self::extractHash($user?->data->get('password'));
        if ($user === null || $hash === null || ! password_verify($password, $hash)) {
            $this->attempts->recordFailure($ip);
            $remaining = $this->attempts->remainingAttempts($ip);
            $key = $remaining > 0 ? 'error_login' : 'error_max_login_attempts';
            $this->editor->addMsg('error', $this->t($key, [
                'count' => $remaining > 0
                    ? (string) $remaining
                    : (string) $this->attempts->lockoutMinutes(),
            ]));
            return;
        }

        $this->editor->session->set('loggedin', true);
        $this->editor->session->set('userid', $user->id);
        $this->editor->csrf->clear();
        $this->attempts->reset();
        $this->editor->flashMsg('success', $this->t('successful_login'));
        $this->redirect($this->editor->siteUrl . '/');
    }

    private function logoutAction(): void
    {
        if ($this->csrfRequired() && ! $this->editor->csrf->validate(
            $this->editor->input->getString('tokenName'),
            $this->editor->input->getString('tokenValue'),
        )) {
            $this->editor->addMsg('error', $this->t('error_csrf_token_mismatch') ?: 'CSRF token invalid.');
            return;
        }
        $this->editor->session->remove('loggedin');
        $this->editor->session->remove('userid');
        $this->editor->csrf->clear();
        $this->editor->flashMsg('success', $this->t('successful_logout'));
        $this->redirect($this->editor->siteUrl . '/auth/');
    }

    private function renderLoginForm(): string
    {
        $tokenName  = 'login_token';
        $tokenValue = $this->editor->csrf->token($tokenName);

        $html  = '<h1>' . htmlspecialchars($this->t('login_header'), \ENT_QUOTES) . '</h1>';
        $html .= '<form id="login-form" action="" method="post">';
        $html .= '<div class="form-control">';
        $html .= '<label for="username">' . htmlspecialchars($this->t('username_label'), \ENT_QUOTES) . '</label>';
        $html .= '<input type="text" id="username" name="username" autocomplete="username">';
        $html .= '</div>';
        $html .= '<div class="form-control">';
        $html .= '<label for="pass">' . htmlspecialchars($this->t('password_label'), \ENT_QUOTES) . '</label>';
        $html .= '<input type="password" id="pass" name="password" autocomplete="current-password">';
        $html .= '</div>';
        $html .= '<input type="hidden" name="action" value="login">';
        $html .= '<input type="hidden" name="tokenName" value="' . htmlspecialchars($tokenName, \ENT_QUOTES) . '">';
        $html .= '<input type="hidden" name="tokenValue" value="' . htmlspecialchars($tokenValue, \ENT_QUOTES) . '">';
        $html .= '<button class="icons button primary" type="submit" name="submit">';
        $html .= '<i class="gg-log-in"></i><span>' . htmlspecialchars($this->t('login_button'), \ENT_QUOTES) . '</span>';
        $html .= '</button>';
        $html .= '</form>';
        return $html;
    }

    private function csrfRequired(): bool
    {
        return (bool) ($this->editor->config['protectCSRF'] ?? true);
    }

    /**
     * @param array<string, string> $vars
     */
    private function t(string $key, array $vars = []): string
    {
        $template = $this->editor->i18n[$key] ?? $key;
        if ($vars === []) {
            return $template;
        }
        // Legacy templates use [[var]] placeholders.
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

    /**
     * The 2.0 PasswordFieldType writes the bcrypt hash as a plain string,
     * but items migrated from 1.x still carry the legacy
     * `PasswordFieldValue` wrapper `{__class, password, salt}`. Accept both.
     */
    private static function extractHash(mixed $value): ?string
    {
        if (\is_string($value) && $value !== '') {
            return $value;
        }
        if (\is_array($value) && \is_string($value['password'] ?? null) && $value['password'] !== '') {
            return $value['password'];
        }
        return null;
    }

    private static function clientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CLIENT_IP']        ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR']  ?? null,
            $_SERVER['REMOTE_ADDR']           ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (! \is_string($candidate) || $candidate === '') {
                continue;
            }
            $candidate = trim(explode(',', $candidate)[0]);
            if (filter_var($candidate, \FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }
        return '0.0.0.0';
    }
}
