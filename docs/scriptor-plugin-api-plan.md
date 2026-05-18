# Scriptor Plugin API: design plan

Status: **plan drafted, awaiting review**
Driver: scriptor-cms.dev rebuild. The Info theme grew a markdown-document
route as an in-theme override. That works for one site but reveals two
gaps in Scriptor 2.x: the frontend has no extension surface outside
theme inheritance, and the editor's module table is hard-coded. The
Scriptor team can't yet build a reusable feature without forking a
theme. This plan fixes that.

> Implementation lives in this repo under
> [`boot/`](../boot/) (PHP source) plus theme-side adapters in the
> consumer bundle (`bigin/scriptor-cms-ops`). The first plugin to
> consume the new API, `bigins/scriptor-markdown-pages`, gets its own
> repository.

---

## 1. Goal and non-goals

### Goal

Give Scriptor a first-class plugin system that satisfies four
requirements:

1. **Frontend extensibility without theme inheritance.** Any installed
   plugin can intercept the request pipeline, resolve pages from
   alternative sources (markdown files, external APIs, in-process
   generators), filter rendered output, or fire side effects after
   render. Plugins are pure composer packages.
2. **Editor extensibility.** Plugins can register new editor modules,
   add sidebar menu entries, and ship their own admin pages. The
   existing `EditorRouter` if-ladder becomes a registry walk.
3. **Composer-native discovery.** No manual config edit to enable a
   plugin. `composer require bigins/scriptor-markdown-pages` and the
   plugin loads on the next request, much like Laravel's package
   auto-discovery.
4. **PSR-14 events as the wire format.** Plugins subscribe to typed
   event objects, not to magic strings. iManager already ships the
   PSR-14 dispatcher infrastructure; Scriptor reuses it for frontend
   and editor events instead of inventing a parallel mechanism.

### Non-goals

- Cross-version plugin compatibility guarantees. Scriptor 2.x is the
  contract; 3.x can break it. Plugins declare a `scriptor` constraint
  in their composer.json.
- Sandboxing. Plugins run with full app privileges. We're not Drupal.
- Hot-reload at request time. Adding a plugin requires the standard
  composer install + restart cycle.
- A web UI for installing plugins from a registry. Out of scope for
  v1. The existing `Install` editor module (which discovers
  `site/modules/*` packages) is a separate legacy path that we'll
  retire once the new system is settled, but not in this round.

---

## 2. State of the art today

### Frontend pipeline

`public/index.php:48-59` boots `_ext.php`, which creates `$site` as a
theme-specific subclass of `Scriptor\Boot\Frontend\Site`. `Site::execute()`
(line 125) resolves a `Page` by walking the URL segments, looking up
the slug in iManager's `items` table, and validating the parent chain.

There is no event dispatch. The only extension surface is overriding
`execute()` in a theme subclass. The Info theme uses this trick to add
the markdown-document route (`InfoTheme::execute()` short-circuits to
its own resolver before calling `parent::execute()`). That's the
fork-a-theme pattern we want to eliminate.

### Editor pipeline

`public/index.php` delegates `/<adminPath>/*` to
`editor/index.php`, which instantiates `Scriptor\Boot\Editor\Editor`
and runs `EditorRouter::execute()`. The router is an if-ladder over
the first URL segment, dispatching to five hard-coded modules:
`auth`, `pages`, `profile`, `settings`, `install`, plus the `api`
sub-tree.

To register a new editor surface today, you either (a) edit
`EditorRouter.php` upstream or (b) drop a module into `site/modules/`
which the legacy `InstallModule` discovers. Path (a) is a Scriptor
PR every time; path (b) bypasses Composer and won't survive into a
modern dependency story.

### What already exists that we can build on

- **DI container:** `League\Container\Container` is process-wide via
  `Scriptor\Boot\App::container()`.
- **PSR-14 plumbing:** iManager ships `SubscriberListenerProvider`
  and `SyncEventDispatcher`. Scriptor already subscribes to iManager
  storage events (`ItemDeleted`, `ItemUpdated`) in
  `ImanagerBootstrap::wireDomainEventListeners()`.
- **Theme `_ext.php` boot point:** the place to call into the plugin
  manager before `$site->execute()` runs.

### What is missing

- Plugin discovery
- Plugin contract and registration API
- Frontend event catalog (PageResolving, PageResolved,
  ContentRendering, Rendered, RouteNotFound)
- Editor menu and module registries
- A first non-trivial plugin to validate the design

---

## 3. Architecture

### 3.1 Plugin discovery via Composer

Plugins are composer packages of type `scriptor-plugin`:

```json
{
    "name": "bigins/scriptor-markdown-pages",
    "description": "File-system backed markdown pages for Scriptor.",
    "type": "scriptor-plugin",
    "require": {
        "bigins/scriptor": "^2.1",
        "league/commonmark": "^2.5"
    },
    "extra": {
        "scriptor": {
            "plugin": "Bigins\\ScriptorMarkdownPages\\Plugin"
        }
    },
    "autoload": {
        "psr-4": { "Bigins\\ScriptorMarkdownPages\\": "src/" }
    }
}
```

At boot, `PluginManager::discover()` reads
`vendor/composer/installed.json`, filters for `type:scriptor-plugin`,
and collects the FQCNs in `extra.scriptor.plugin`. No glob over the
filesystem, no convention magic; the composer.json is the manifest.

Opt-out for tests or staged rollouts: an array of FQCNs in
`data/settings/scriptor-config.php` under `plugins.disabled`
short-circuits the loader for those classes.

### 3.2 Plugin contract

```php
namespace Scriptor\Boot\Plugin;

interface Plugin
{
    /** Human-readable identifier; shown in /editor/plugins and logs. */
    public function name(): string;

    /** Semantic version, used for diagnostics only (no enforcement). */
    public function version(): string;

    /**
     * Register the plugin against the application surface. Called once
     * per request, after the iManager container is booted, before any
     * routing or rendering happens.
     */
    public function register(PluginContext $context): void;
}
```

A plugin's `register()` is the only place where the framework hands
over control to plugin code at boot time. Everything else happens via
the event subscriptions or module factories it registered there.

### 3.3 PluginContext

`PluginContext` is the registration surface. It exposes typed methods
for each thing a plugin can do:

```php
final class PluginContext
{
    public function container(): Container;                          // DI bindings
    public function subscribe(string $event, callable $handler): void;  // PSR-14 listener
    public function registerEditorModule(string $slug, callable $factory): void;
    public function addEditorMenuItem(EditorMenuItem $item): void;
    public function declareSetting(SettingDefinition $def): void;    // for future settings UI
}
```

Plugins never reach into framework internals directly. The context
mediates everything, which means we can rework the wiring under it
without breaking plugins on every refactor.

### 3.4 PluginManager

```php
final class PluginManager
{
    public function __construct(
        private readonly Container $container,
        private readonly SubscriberListenerProvider $listeners,
        private readonly EditorRegistry $editor,
        private readonly array $vendorDir,
        private readonly array $disabled,
    ) {}

    public function discover(): array;        // Plugin[] from installed.json
    public function bootAll(): void;          // instantiate, call register()
}
```

`bootAll()` is called from `boot.php` after `ImanagerBootstrap::create()`
returns. Themes load AFTER plugins, so a theme can override what a
plugin did. That ordering matches Hetzner-deploy intuition: deep
infra first, surface customisation last.

### 3.5 Frontend events (PSR-14)

Five events form the request lifecycle. All live under
`Scriptor\Boot\Events\Frontend\`:

| Event | Fired by | Carries | Purpose |
|---|---|---|---|
| `PageResolving` | `Site::execute()` before DB lookup | `UrlSegments`, `Request`, mutable `PageResolution` slot | Plugins can resolve the page themselves and write into the slot. First non-null wins; the standard DB lookup runs only if the slot stays null. |
| `PageResolved` | `Site::execute()` after a resolver wins | resolved `Page` or virtual page envelope | Side effects: logging, ACL checks that can throw, breadcrumbs. |
| `ContentRendering` | `Site::renderContent()` before sanitizer | mutable string slot | Plugins can substitute content. Markdown plugin uses this to inject parsed HTML when the page is a `.md` file. |
| `Rendered` | After `template.php` finishes | full output buffer | Post-process: inject analytics, rewrite asset URLs, attach a Server-Timing header. |
| `RouteNotFound` | `Site::throw404()` before render | `UrlSegments`, mutable `PageResolution` slot | Last chance. If a plugin populates the slot, render proceeds with that page instead of 404. |

Each event class is a small readonly DTO plus, where listeners need to
contribute, a mutable result holder (`PageResolution`,
`ContentResult`) so we don't traffic in raw references.

### 3.6 Editor extension hooks

Two registries, both populated during `Plugin::register()` and
consumed by `EditorRouter` and the editor layout:

**`EditorModuleRegistry`** holds `slug => factory(): Module`. The
current router if-ladder is replaced by a registry lookup. The
factory closure receives the `Container` so it can resolve its own
dependencies. The hard-coded `pages`, `profile`, `settings`, `auth`,
`install`, `api` paths register themselves through this same
mechanism at boot (in a small `EditorCoreModulesPlugin` that ships
with Scriptor), so the registry is the only path.

**`EditorMenuRegistry`** holds an ordered list of menu items
(label, href, icon, weight, condition). The sidebar layout iterates
this list, filtered by `condition(Editor): bool` so a plugin can
hide its entry when the current user lacks the right.

### 3.7 First-party plugin examples

The plan reframes existing Scriptor surfaces as first-party plugins
inside the Scriptor repo, distributed as Composer "replace" entries
so they auto-load:

- `Scriptor\Boot\Plugin\CoreEditorPlugin` registers the five built-in
  editor modules + their menu items. Removing this plugin via
  `plugins.disabled` strips the editor surface for headless deployments.
- `Scriptor\Boot\Plugin\DbPagesResolverPlugin` subscribes the
  built-in DB slug lookup to `PageResolving`. Today this is hard-wired
  in `Site::execute()`; this plan moves it into a plugin so the
  resolver pipeline is uniform.

The point of doing it this way is dogfooding: any pattern available to
third-party plugins is the same one Scriptor uses for its own features.

---

## 4. Migration path

### 4.1 InfoTheme stops carrying the markdown route

`scriptor-cms-ops/scriptor-cms-site/theme/themes/info/lib/Markdown/*`
moves to a new repo, `bigins/scriptor-markdown-pages`. The plugin
subscribes to `PageResolving` and pulls from
`content/<track>/<...>.md` exactly as the in-theme version does
today.

InfoTheme keeps only the markdown-section template (chrome + sidebar
+ scripts) and the styles.css markdown-prose section. The composer
require shifts from `league/commonmark` to
`bigins/scriptor-markdown-pages`.

### 4.2 Editor gets a "Documentation" surface

The new plugin registers a read-only editor module under `/editor/docs/`
that walks the `content/` filesystem and renders each `.md` through
the same parser the frontend uses. No write actions; edits still
happen externally via Git. The surface answers the dogfooding
question "where do I see my pages in the editor?".

### 4.3 Backwards compatibility for in-tree themes

The existing themes (`info`, `basic`) keep working without plugin
awareness. The new dispatch order is:

```
PluginManager::bootAll()      ← plugins register themselves
$site = new InfoTheme(...)     ← theme override of Site
$site->execute()               ← still callable directly; internally now dispatches PageResolving first
```

Theme code that overrode `Site::execute()` (today the only way) keeps
working. The override happens before the event dispatch fires, so a
theme can still pre-empt plugins if it wants to.

---

## 5. Phase roadmap

| Phase | Goal | Repo | Tracking task |
|---|---|---|---|
| 1 | This plan-PR | Scriptor | #92 |
| 2 | PluginManager + discovery + Plugin/PluginContext interfaces + tests | Scriptor | #93 |
| 3 | PSR-14 frontend events + DbPagesResolverPlugin | Scriptor | #94 |
| 4 | EditorModuleRegistry + EditorMenuRegistry + CoreEditorPlugin refactor of EditorRouter | Scriptor | #95 |
| 5 | `bigins/scriptor-markdown-pages` new repo (plugin extract + editor "Documentation" module) | new repo | #96 |
| 6 | InfoTheme cleanup: drop `lib/Markdown/*`, depend on the plugin instead | scriptor-cms-ops | #97 |
| 7 | scriptor-cms-site Phase B (sitemap refactor + track sidebars) resumes | scriptor-cms-ops | #84 |

Phases 2-4 each ship one PR in the Scriptor repo. Phases 5-6 ship
PRs in the plugin repo and ops repo respectively. The Scriptor-side
work blocks everything downstream, so phases 2 through 4 are the
critical path.

---

## 6. Success criteria

A phase is done when:

1. **Smoke green:** the standard scriptor-cms-site smokes pass
   locally (Home, /developer-guide/, /developer-guide/welcome/,
   /editor/pages/) and the editor still routes its built-in modules.
2. **Plugin behaviour intact:** the plugin under test is enabled
   and exhibits its effect end-to-end (markdown route resolves a
   `.md`, editor sidebar shows the plugin's menu item).
3. **Plugin behaviour rollback:** disabling the plugin via
   `plugins.disabled` removes its effect; the system reverts to
   pre-plugin behaviour.
4. **No regressions:** existing themes (info, basic) and the demo
   bundle still render. CSP headers stay A-grade. nginx logs show no
   new 500s for 24h post-deploy.
5. **Discovery loop:** any Scriptor smells found while implementing
   are either merged or filed in BACKLOG.md.

---

## 7. Open questions

1. **Plugin order.** Plugin `register()` runs in `installed.json`
   order today. Should we expose a `weight()` method, or accept
   implicit order? Lean towards implicit; document the implication.
2. **Per-request vs per-process boot.** Scriptor boots fresh per
   request. Plugins run `register()` every request. For a
   100-plugin install this is wasteful. Out of scope for v1; revisit
   when we have a real plugin ecosystem to measure.
3. **Plugin uninstall hooks.** A plugin may want to clean up its
   editor modules / event subscriptions on uninstall. The composer
   `post-uninstall-cmd` event covers `vendor/` cleanup; do we need
   a Scriptor-side hook for DB tables, files, settings? Probably
   yes, deferred to v1.1.
4. **Naming.** `scriptor-plugin` (composer type) or
   `bigins/scriptor-X` (package namespace)? The plan uses both.
   Decide before Phase 2 lands so the discovery key is stable.
5. **PSR-14 vs typed Hook.** PSR-14 dispatches event objects and
   passes them through every listener; it does not return values.
   `PageResolving` uses a mutable slot to work around this. An
   alternative is a typed-hook interface
   (`PageResolver { resolve(...): ?PageResolution }`) that is not
   strictly PSR-14. The plan picks PSR-14 for consistency with
   iManager events; revisit if the mutable-slot pattern feels
   awkward in practice.

---

## 8. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Boot overhead for every request (plugin discovery hits installed.json) | Cache discovered plugin list to `data/cache/plugins.php`; invalidate on composer events. |
| EditorRouter refactor breaks every editor surface | Phase 4 lands with the existing five modules ported to the new registry in the same PR, plus a smoke matrix in the PR body. |
| Theme override semantics drift (theme overrides expected old execute() path) | Migration note in the phase 3 PR; existing in-tree themes (info, basic) get touched in the same PR if their override patterns need adjustment. |
| Composer auto-discovery picks up untrusted plugins after `composer require` | Same trust model as Composer itself: if you required it, you trust it. `plugins.disabled` lets ops disable a specific plugin without uninstalling it. |
