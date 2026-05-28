<?php

declare(strict_types=1);

namespace Scriptor\Boot\Plugin;

/**
 * Read / write the installed-lifecycle-plugins state file.
 *
 * Persists at `<scriptor-root>/data/plugin-states.json`:
 *
 *     {
 *         "studenten-frankfurt/katalog": {
 *             "version": "0.1.0",
 *             "installed_at": 1748419200,
 *             "registered_fields": ["price", "deposit", "equipment"]
 *         }
 *     }
 *
 * Only plugins implementing {@see LifecyclePlugin} are tracked here —
 * stateless ones own no DB state and need no lifecycle bookkeeping.
 *
 * Presence in the file = "installed by `bin/scriptor plugin:install`".
 * Absence = "never installed via CLI, or cleanly uninstalled". The CLI
 * commands are the only callers that mutate the file; the framework
 * reads it during orphan detection at boot.
 *
 * Atomic writes: serialise to a tmp file in the same directory and
 * rename. The states file lives in `data/`, which is git-ignored, so
 * its absence on a fresh checkout is normal — `read()` returns an
 * empty array in that case rather than erroring.
 */
final class PluginStateManager
{
    public const STATE_FILE = 'data/plugin-states.json';

    public function __construct(
        private readonly string $scriptorRoot,
    ) {}

    /**
     * @return array<string, array{version: string, installed_at: int, registered_fields?: list<string>}>
     */
    public function all(): array
    {
        $path = $this->statePath();
        if (! \is_file($path)) {
            return [];
        }
        $raw = \file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = \json_decode($raw, true);
        if (! \is_array($decoded)) {
            throw new \RuntimeException(
                "Plugin state file is not a JSON object: {$path}",
            );
        }
        /** @var array<string, array{version: string, installed_at: int, registered_fields?: list<string>}> $decoded */
        return $decoded;
    }

    public function isInstalled(string $packageName): bool
    {
        return \array_key_exists($packageName, $this->all());
    }

    /**
     * @return array{version: string, installed_at: int, registered_fields?: list<string>}|null
     */
    public function get(string $packageName): ?array
    {
        return $this->all()[$packageName] ?? null;
    }

    /**
     * @param list<string> $registeredFields
     */
    public function markInstalled(
        string $packageName,
        string $version,
        array $registeredFields = [],
    ): void {
        $states = $this->all();
        $entry  = [
            'version'      => $version,
            'installed_at' => \time(),
        ];
        if ($registeredFields !== []) {
            $entry['registered_fields'] = \array_values($registeredFields);
        }
        $states[$packageName] = $entry;
        $this->write($states);
    }

    public function unmark(string $packageName): void
    {
        $states = $this->all();
        if (! \array_key_exists($packageName, $states)) {
            return;
        }
        unset($states[$packageName]);
        $this->write($states);
    }

    /**
     * @param array<string, array{version: string, installed_at: int, registered_fields?: list<string>}> $states
     */
    private function write(array $states): void
    {
        $path = $this->statePath();
        $dir  = \dirname($path);
        if (! \is_dir($dir) && ! @\mkdir($dir, 0o755, true) && ! \is_dir($dir)) {
            throw new \RuntimeException("Cannot create plugin-state directory: {$dir}");
        }
        $body = \json_encode($states, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode plugin states to JSON');
        }
        $tmp = $path . '.tmp.' . \bin2hex(\random_bytes(4));
        if (\file_put_contents($tmp, $body . "\n") === false) {
            throw new \RuntimeException("Failed to write plugin state tmp file: {$tmp}");
        }
        if (! @\rename($tmp, $path)) {
            @\unlink($tmp);
            throw new \RuntimeException("Failed to move plugin state file into place: {$path}");
        }
    }

    private function statePath(): string
    {
        return $this->scriptorRoot . '/' . self::STATE_FILE;
    }
}
