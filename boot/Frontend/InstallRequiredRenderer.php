<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

/**
 * Stand-alone error renderer for the "you ran composer install but
 * forgot bin/scriptor install" case.
 *
 * Cannot lean on a theme: the theme bootstrap is the path that just
 * threw, because the Pages or Users category does not exist yet.
 * Cannot lean on iManager's PSR-3 logger either; we want HTML in
 * front of a human, not a log line.
 *
 * The renderer detects its own trigger (the RuntimeException thrown
 * by `PageRepository::__construct()` or `UserRepository::__construct()`
 * when their category is missing) by matching the exception message
 * shape, so the front controller can wrap the whole theme/editor
 * bootstrap with a single try/catch and only intercept this one case.
 */
final class InstallRequiredRenderer
{
    private function __construct()
    {
    }

    /**
     * True when the exception is the well-known "missing Pages or
     * Users category" signal, so the front controller knows to call
     * {@see render()} instead of falling through to the generic 500.
     */
    public static function matches(\Throwable $exception): bool
    {
        if (! $exception instanceof \RuntimeException) {
            return false;
        }
        $message = $exception->getMessage();
        return \str_contains($message, 'Category with slug "pages" not found')
            || \str_contains($message, 'Category with slug "users" not found');
    }

    /**
     * Emit a self-contained HTML page with the next command to run
     * and a link to the install walkthrough. Sets HTTP 503 because
     * the install is incomplete, not because the request was bad.
     */
    public static function render(): void
    {
        if (! headers_sent()) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo self::html();
    }

    private static function html(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scriptor, installation required</title>
    <style>
        :root {
            --ink: #1a1a1a;
            --muted: #555;
            --bg: #fafafa;
            --card: #fff;
            --accent: #c14e2c;
            --border: #e5e5e5;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font: 16px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI",
                  Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--ink);
            background: var(--bg);
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        main {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 2rem 2.25rem;
            max-width: 640px;
            width: 100%;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        h1 {
            margin: 0 0 1rem;
            font-size: 1.5rem;
            color: var(--accent);
        }
        p { margin: 0 0 1rem; color: var(--muted); }
        p strong { color: var(--ink); }
        pre {
            background: #f4f4f4;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: .75rem 1rem;
            margin: 1.25rem 0;
            overflow-x: auto;
            font: 14px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        code { font: inherit; }
        a { color: var(--accent); }
        ul { margin: .5rem 0 1rem 1.25rem; padding: 0; color: var(--muted); }
        li { margin: .25rem 0; }
        footer {
            margin-top: 1.75rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: .875rem;
        }
    </style>
</head>
<body>
<main>
    <h1>Scriptor needs to finish installing</h1>
    <p>
        Composer dropped the code in place, but the database is
        empty: no <strong>Pages</strong> or <strong>Users</strong>
        category exists yet. One command from the project root
        seeds them along with an admin user and a Home page:
    </p>
    <pre><code>php bin/scriptor install</code></pre>
    <p>
        You will be asked to confirm the database path and pick an
        admin password (8 characters minimum). After the command
        finishes, reload this page.
    </p>
    <p>
        For Docker, CI, or scripted setups pass the password
        without prompting:
    </p>
    <pre><code>SCRIPTOR_ADMIN_PASSWORD='your-strong-secret' \
  php bin/scriptor install --yes</code></pre>
    <footer>
        Full walkthrough, troubleshooting, and security notes:
        <a href="https://github.com/bigin/Scriptor/blob/master/docs/install.md">docs/install.md</a>.
    </footer>
</main>
</body>
</html>

HTML;
    }
}
