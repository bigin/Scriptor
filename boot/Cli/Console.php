<?php

declare(strict_types=1);

namespace Scriptor\Boot\Cli;

/**
 * Thin wrapper around stdin/stdout/stderr.
 *
 * Exists so InstallCommand and PasswordPrompt can be unit-tested with
 * an in-memory replacement without `fopen('php://temp')` dancing in
 * every test. Production code uses `new Console()`.
 */
class Console
{
    /** @var resource */
    protected $stdin;
    /** @var resource */
    protected $stdout;
    /** @var resource */
    protected $stderr;

    public function __construct()
    {
        $this->stdin  = \STDIN;
        $this->stdout = \STDOUT;
        $this->stderr = \STDERR;
    }

    public function writeln(string $line): void
    {
        \fwrite($this->stdout, $line . "\n");
    }

    public function errln(string $line): void
    {
        \fwrite($this->stderr, $line . "\n");
    }

    public function prompt(string $label): string
    {
        \fwrite($this->stdout, $label);
        $line = \fgets($this->stdin);
        return $line === false ? '' : \rtrim($line, "\r\n");
    }

    /**
     * Read a line with echo suppressed via `stty -echo`. Falls back to
     * normal echo if stty is unavailable (Windows, weird terminals).
     * Returns the empty string when no TTY is attached.
     */
    public function promptSecret(string $label): string
    {
        if (! $this->stdinIsTty()) {
            return '';
        }
        \fwrite($this->stdout, $label);
        $sttyOriginal = $this->trySttyToggle('-echo');
        try {
            $line = \fgets($this->stdin);
        } finally {
            if ($sttyOriginal !== null) {
                \shell_exec('stty ' . \escapeshellarg($sttyOriginal));
            }
            \fwrite($this->stdout, "\n");
        }
        return $line === false ? '' : \rtrim($line, "\r\n");
    }

    public function stdinIsTty(): bool
    {
        return \stream_isatty($this->stdin);
    }

    /**
     * Toggle a stty mode. Returns the previous settings string so the
     * caller can restore it, or null when stty is unavailable.
     */
    private function trySttyToggle(string $mode): ?string
    {
        $current = \shell_exec('stty -g 2>/dev/null');
        if ($current === null || \trim((string) $current) === '') {
            return null;
        }
        \shell_exec('stty ' . \escapeshellarg($mode) . ' 2>/dev/null');
        return \trim((string) $current);
    }
}
