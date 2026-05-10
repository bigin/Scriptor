<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Profile;

use Imanager\Domain\Item;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\UserRepository;

/**
 * Profile module — the logged-in user edits their own name, email, and
 * (optionally) password.
 *
 *   /editor/profile/         redirect to /edit/?profile=<currentUserId>
 *   /editor/profile/edit/    render the form
 *   POST action=save-profile validate + persist + redirect
 *
 * Password handling preserves the migrated 1.x `PasswordFieldValue`
 * wrapper shape (`{__class, password, salt}`) when present — Auth
 * recognises both that shape and a plain bcrypt hash, so a password
 * change written here stays compatible with rows that have already
 * been re-saved as plain hashes alongside ones that haven't.
 */
final class ProfileModule
{
    private const MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private readonly Editor $editor,
        private readonly UserRepository $users,
    ) {}

    public function execute(): void
    {
        $currentId = $this->editor->currentUserId();
        if ($currentId === null) {
            // Auth gate guarantees this on `/editor/profile`, but defend
            // against direct callers anyway.
            $this->redirect($this->editor->siteUrl . '/auth/');
        }

        $sub = $this->editor->urlSegments->get(1);
        if ($sub === null) {
            $this->redirect($this->editor->siteUrl . '/profile/edit/?profile=' . $currentId);
        }

        if ($sub !== 'edit') {
            http_response_code(404);
            $this->editor->pageTitle = 'Profile - Scriptor';
            $this->editor->pageContent = '<h1>404</h1><p>Unknown profile sub-route.</p>';
            return;
        }

        if ($this->editor->input->postString('action') === 'save-profile') {
            $this->saveAction($currentId);
            return;
        }

        $this->renderEdit($currentId);
    }

    private function saveAction(int $currentId): void
    {
        $user = $this->users->find($currentId);
        if ($user === null) {
            $this->editor->addMsg('error', $this->t('error_module') ?: 'User not found.');
            $this->renderEdit($currentId);
            return;
        }

        if (! $this->csrfPasses($this->editor->input->postString('tokenName'), $this->editor->input->postString('tokenValue'))) {
            $this->editor->addMsg('error', $this->t('error_csrf_token_mismatch'));
            $this->renderEdit($currentId);
            return;
        }

        $name  = $this->editor->sanitizer->text(str_replace('"', '', $this->editor->input->postString('username')));
        $email = $this->editor->sanitizer->email($this->editor->input->postString('email'));
        if ($name === '' || $email === null || $email === '') {
            $this->editor->addMsg('error', $this->t('profile_incomplete'));
            $this->renderEdit($currentId);
            return;
        }

        if ($this->users->nameTaken($name, exceptId: $currentId)) {
            $this->editor->addMsg('error', 'A user with that name already exists.');
            $this->renderEdit($currentId);
            return;
        }

        $passwordRaw  = $this->editor->input->postString('password');
        $passwordConf = $this->editor->input->postString('password_confirm');
        $passwordValue = $user->data->get('password'); // keep existing on no-change

        if ($passwordRaw !== '') {
            if (mb_strlen($passwordRaw) < self::MIN_PASSWORD_LENGTH) {
                $this->editor->addMsg('error', $this->t('short_password'));
                $this->renderEdit($currentId);
                return;
            }
            if ($passwordRaw !== $passwordConf) {
                $this->editor->addMsg('error', $this->t('error_password_comparison'));
                $this->renderEdit($currentId);
                return;
            }
            $passwordValue = self::buildPasswordPayload($passwordRaw, $passwordValue);
        }

        $data = $this->preserveExistingData($user);
        $data['email']    = $email;
        $data['password'] = $passwordValue;

        $now = time();
        $updated = new Item(
            id:         $user->id,
            categoryId: $user->categoryId,
            name:       $name,
            label:      $user->label,
            position:   $user->position,
            active:     $user->active,
            data:       $data,
            created:    $user->created,
            updated:    $now,
        );

        try {
            $this->users->save($updated);
        } catch (\Throwable $e) {
            $this->editor->addMsg('error', 'Save failed: ' . $e->getMessage());
            $this->renderEdit($currentId);
            return;
        }

        $this->editor->flashMsg('success', $this->t('profile_successful_saved'));
        $this->redirect($this->editor->siteUrl . '/profile/edit/?profile=' . $currentId);
    }

    private function renderEdit(int $currentId): void
    {
        $user = $this->users->find($currentId);
        if ($user === null) {
            $this->editor->pageTitle = 'Profile - Scriptor';
            $this->editor->pageContent = '<h1>404</h1><p>User not found.</p>';
            http_response_code(404);
            return;
        }

        $this->editor->pageTitle = 'Profile editor - Scriptor';
        $this->editor->breadcrumbs = sprintf(
            '<li><span>%s</span></li>',
            htmlspecialchars($this->t('profile_menu'), \ENT_QUOTES),
        );

        $token = $this->editor->csrf->token('profile');
        $email = (string) ($user->data->get('email') ?? '');

        $i = static fn(string $s): string => htmlspecialchars($s, \ENT_QUOTES);

        $html  = '<h1>' . $i($this->t('profile_editor_header')) . '</h1>';
        $html .= '<form id="profile-form" action="./?profile=' . (int) $currentId . '" method="post">';
        $html .= '<div class="form-control">'
              . '<label class="required" for="username">' . $i($this->t('username_label')) . '</label>'
              . '<input name="username" id="username" type="text" value="' . $i((string) $user->name) . '" autocomplete="username">'
              . '</div>';
        $html .= '<div class="form-control">'
              . '<label class="required" for="email">' . $i($this->t('email_label')) . '</label>'
              . '<input name="email" id="email" type="email" value="' . $i($email) . '" autocomplete="email">'
              . '</div>';
        $html .= '<div class="form-control">'
              . '<label for="pass">' . $i($this->t('new_password_label')) . '</label>'
              . '<input name="password" id="pass" type="password" autocomplete="new-password" minlength="' . self::MIN_PASSWORD_LENGTH . '">'
              . '</div>';
        $html .= '<div class="form-control">'
              . '<label for="pass_confirm">' . $i($this->t('password_confirm_label')) . '</label>'
              . '<input name="password_confirm" id="pass_confirm" type="password" autocomplete="new-password" minlength="' . self::MIN_PASSWORD_LENGTH . '">'
              . '</div>';
        $html .= '<input type="hidden" name="action" value="save-profile">';
        $html .= '<input type="hidden" name="tokenName" value="profile">';
        $html .= '<input type="hidden" name="tokenValue" value="' . $i($token) . '">';
        $html .= '<button class="icons primary" type="submit" id="save" name="save" value="1">'
              . '<i class="gg-drive"></i><span>&nbsp;' . $i($this->t('save_button')) . '</span>'
              . '</button>';
        $html .= '</form>';

        $this->editor->pageContent = $html;
    }

    /**
     * @return array<string, mixed>
     */
    private function preserveExistingData(Item $user): array
    {
        $out = [];
        foreach (['role', 'email', 'password'] as $key) {
            if ($user->data->has($key)) {
                $out[$key] = $user->data->get($key);
            }
        }
        return $out;
    }

    /**
     * Builds the value to write back to the `password` field. If the
     * existing field uses the legacy 1.x wrapper shape we keep that
     * shape (only the hash inside changes); otherwise we write a plain
     * string so the modern PasswordFieldType reads it directly.
     */
    private static function buildPasswordPayload(string $rawPassword, mixed $existing): mixed
    {
        $hash = password_hash($rawPassword, \PASSWORD_BCRYPT);
        if (\is_array($existing) && \array_key_exists('password', $existing)) {
            $existing['password'] = $hash;
            return $existing;
        }
        return $hash;
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

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}
