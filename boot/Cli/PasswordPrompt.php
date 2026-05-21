<?php

declare(strict_types=1);

namespace Scriptor\Boot\Cli;

/**
 * Interactive TTY password reader with confirmation.
 *
 * Echo is suppressed via {@see Console::promptSecret()}; this class
 * adds the confirmation loop and the "passwords don't match" retry.
 * Up to three attempts before giving up; an empty string is returned
 * on give-up so the caller's validatePassword() rejects it.
 */
final class PasswordPrompt
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(private readonly Console $console)
    {
    }

    public function readWithConfirmation(int $minLength): string
    {
        if (! $this->console->stdinIsTty()) {
            return '';
        }
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $first = $this->console->promptSecret(
                "Enter admin password ({$minLength}+ chars): "
            );
            $second = $this->console->promptSecret('Confirm admin password: ');
            if ($first === $second && $first !== '') {
                return $first;
            }
            $this->console->errln('Passwords did not match, try again.');
        }
        $this->console->errln('Too many failed attempts.');
        return '';
    }
}
