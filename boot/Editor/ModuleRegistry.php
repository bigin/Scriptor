<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor;

use League\Container\Container;

/**
 * Slug-to-factory lookup for editor modules.
 *
 * The {@see EditorRouter} reads from this registry to resolve which
 * module handles a given URL. Both first-party modules
 * (shipped by {@see Scriptor\Boot\Plugin\CorePlugins\CoreEditorPlugin})
 * and plugin-contributed modules go through the same entry, so the
 * router has one code path instead of an if-ladder.
 *
 * Factories receive the DI container plus the per-request
 * {@see Editor} instance. The latter is not in the container because
 * Editor is constructed in editor/index.php after session boot, which
 * is later than plugin {@see Plugin::register()} runs; passing it as a
 * positional argument keeps factory closures self-contained.
 */
final class ModuleRegistry
{
    /** @var array<string, callable(Container, Editor): Module> */
    private array $factories = [];

    /**
     * Override semantics: re-registering an existing slug replaces the
     * previous factory. Plugins boot in registration order
     * (core first, then discovered), so a third-party plugin can take
     * over an editor route from a core module if it really needs to.
     *
     * @param callable(Container, Editor): Module $factory
     */
    public function register(string $slug, callable $factory): void
    {
        $this->factories[$slug] = $factory;
    }

    public function has(string $slug): bool
    {
        return isset($this->factories[$slug]);
    }

    public function create(string $slug, Container $container, Editor $editor): Module
    {
        if (! isset($this->factories[$slug])) {
            throw new \RuntimeException("Unknown editor module slug: {$slug}");
        }
        return ($this->factories[$slug])($container, $editor);
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->factories);
    }
}
