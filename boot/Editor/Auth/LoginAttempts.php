<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Auth;

use Imanager\Http\SessionStore;

/**
 * IP-scoped failed-login counter persisted in the editor session.
 * Locks the IP out for `lockoutMinutes` once `maxAttempts` failures
 * are recorded; a successful login or a passing `tick()` after the
 * lockout window resets the counter.
 */
final readonly class LoginAttempts
{
    private const SESSION_KEY = 'login_attempts';

    public function __construct(
        private SessionStore $session,
        private int $maxAttempts = 5,
        private int $lockoutMinutes = 5,
    ) {}

    public function isAllowed(string $ip): bool
    {
        $state = $this->state($ip);
        if ($state['attempts'] < $this->maxAttempts) {
            return true;
        }
        if ($state['locked_until'] > 0 && time() >= $state['locked_until']) {
            $this->reset();
            return true;
        }
        return false;
    }

    public function recordFailure(string $ip): void
    {
        $state = $this->state($ip);
        $state['attempts']++;
        if ($state['attempts'] >= $this->maxAttempts) {
            $state['locked_until'] = time() + ($this->lockoutMinutes * 60);
        }
        $this->session->set(self::SESSION_KEY, $state);
    }

    public function remainingAttempts(string $ip): int
    {
        $state = $this->state($ip);
        return max(0, $this->maxAttempts - $state['attempts']);
    }

    public function lockoutMinutes(): int
    {
        return $this->lockoutMinutes;
    }

    public function reset(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    /**
     * @return array{ip: string, attempts: int, locked_until: int}
     */
    private function state(string $ip): array
    {
        $stored = $this->session->get(self::SESSION_KEY);
        if (
            \is_array($stored)
            && ($stored['ip'] ?? null) === $ip
            && \is_int($stored['attempts'] ?? null)
        ) {
            return [
                'ip'           => $ip,
                'attempts'     => $stored['attempts'],
                'locked_until' => (int) ($stored['locked_until'] ?? 0),
            ];
        }
        $fresh = ['ip' => $ip, 'attempts' => 0, 'locked_until' => 0];
        $this->session->set(self::SESSION_KEY, $fresh);
        return $fresh;
    }
}
