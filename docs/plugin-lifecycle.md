# Plugin lifecycle

Scriptor 2 ships two kinds of plugins:

- **Stateless plugins** implement `Scriptor\Boot\Plugin\Plugin`. They
  subscribe to events, register editor modules, contribute nav items.
  They own no DB state and need no lifecycle: `register()` runs once
  per request, that's it.

- **Lifecycle plugins** implement `Scriptor\Boot\Plugin\LifecyclePlugin`
  (which extends `Plugin`). They additionally declare `install()` and
  `uninstall()` — one-shot setup and teardown for plugins that register
  iManager category fields, seed default items, or do other work that
  must run exactly once on install and reverse on removal.

The framework never auto-invokes lifecycle hooks. The operator drives
them through `bin/scriptor plugin:install` and `bin/scriptor plugin:uninstall`.

## The four CLI commands

```
bin/scriptor plugin:list                   # Inventory + state
bin/scriptor plugin:install <package>      # Invoke install(), mark state
bin/scriptor plugin:uninstall <package>    # Invoke uninstall(), clear state
bin/scriptor plugin:cleanup-orphan         # Recovery: state without code
```

### `plugin:list`

Read-only inventory: lists every discovered scriptor-plugin composer
package together with the row in `data/plugin-states.json` (if any).
Four meaningful states:

| KIND      | STATUS           | Meaning                                                       |
|-----------|------------------|---------------------------------------------------------------|
| stateless | discovered       | Plain Plugin, no lifecycle work                               |
| lifecycle | pending install  | LifecyclePlugin not yet `plugin:install`ed                    |
| lifecycle | installed        | LifecyclePlugin tracked in `data/plugin-states.json`          |
| lifecycle | ORPHAN           | State entry without a discovered package (composer-removed)   |

Safe before `bin/scriptor install` on a fresh checkout — no DB access.

### `plugin:install <package>` / `--all`

Bootstraps the iManager container, instantiates the plugin's class,
calls `LifecyclePlugin::install(PluginContext)`, and records the package
in `data/plugin-states.json` on success.

```bash
bin/scriptor plugin:install studenten-frankfurt/katalog
bin/scriptor plugin:install --all          # Every pending lifecycle plugin
bin/scriptor plugin:install --force ...    # Re-run install() on an installed package
```

If `install()` throws, the state file stays unchanged. Operator fixes
the failure and retries — no stale "installed" marker.

### `plugin:uninstall <package>` / `--purge-data`

Invokes `LifecyclePlugin::uninstall(PluginContext)` while the plugin's
class is still loadable, then removes the package from state.json.
After the CLI succeeds, the operator runs `composer remove <package>`
to drop the code itself.

```bash
bin/scriptor plugin:uninstall studenten-frankfurt/katalog
bin/scriptor plugin:uninstall --purge-data ...     # also wipe row values
bin/scriptor plugin:uninstall --force-state-clear  # escape hatch
```

**Data preservation is the default**: the plugin's `uninstall()` body
typically removes schema entries (field definitions, custom categories)
but leaves the per-row JSON values in `items.data` alone, on the
assumption that the operator might reinstall later. Pass `--purge-data`
to forward `purgeDataRequested = true` on the PluginContext — the
plugin's body then chooses to also strip those values from items.

### `plugin:cleanup-orphan`

When the operator forgets the CLI and runs `composer remove <package>`
directly, the package's code is gone but its state entry stays. The
package shows up as ORPHAN in `plugin:list`. `plugin:cleanup-orphan`
without arguments lists every orphan; with a `<package>` argument it
drops that single entry from state.json.

It does **not** touch DB schema entries the orphan plugin registered —
without the plugin's code we don't know what those entries were. The
only automatic cleanup path is: `composer require` the plugin back,
then run a clean `plugin:uninstall`. Manual SQL is the alternative.

## Writing a lifecycle plugin

A minimal `LifecyclePlugin` looks like:

```php
namespace Acme\Products;

use Imanager\Domain\Field;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Scriptor\Boot\Plugin\LifecyclePlugin;
use Scriptor\Boot\Plugin\PluginContext;
use Scriptor\Boot\Events\Editor\PageFormRendering;
use Scriptor\Boot\Events\Editor\PageSaving;

final class Plugin implements LifecyclePlugin
{
    public function name(): string    { return 'acme/products'; }
    public function version(): string { return '1.0.0'; }

    public function register(PluginContext $context): void
    {
        // Per-request wiring (subscribe to form events, etc.)
        $context->subscribe(PageFormRendering::class, [$this, 'onFormRendering']);
        $context->subscribe(PageSaving::class,        [$this, 'onSaving']);
    }

    public function install(PluginContext $context): void
    {
        $cats   = $context->container()->get(CategoryRepository::class);
        $fields = $context->container()->get(FieldRepository::class);
        $pagesId = $cats->findBySlug('pages')->id;

        $fields->ensure(Field::text($pagesId, 'price',   'Price')->position(20));
        $fields->ensure(Field::text($pagesId, 'deposit', 'Deposit')->position(21));
    }

    public function uninstall(PluginContext $context): void
    {
        $cats   = $context->container()->get(CategoryRepository::class);
        $fields = $context->container()->get(FieldRepository::class);
        $pagesId = $cats->findBySlug('pages')->id;

        foreach (['price', 'deposit'] as $name) {
            $field = $fields->findByName($pagesId, $name);
            if ($field !== null && $field->id !== null) {
                $fields->delete($field->id);
            }
        }

        if ($context->purgeDataRequested) {
            // Operator passed --purge-data. Wipe row values for the
            // fields above from items.data. Implementation omitted
            // here — typically a direct SQL UPDATE removing the JSON
            // keys per row.
        }
    }

    // ...event handlers...
}
```

### What `install()` may do

`install()` runs after the iManager container is up (schema migrations
applied, repositories registered). Safe operations:

- `FieldRepository->ensure(...)` to declare category fields
- `CategoryRepository->ensure(...)` to declare new categories
- `ItemRepository->save(...)` to seed default rows

What it must not do:

- Subscribe to events — that's `register()`'s job; install runs once,
  the subscription would never fire after install completes.
- Register editor modules / menu items — same reason.
- Anything depending on plugin boot order — install runs in isolation.

### What `uninstall()` should do

- Reverse the schema work from `install()` (delete fields, delete
  categories, delete seed rows).
- Read `$context->purgeDataRequested` and, if true, also strip the
  row values the plugin owned from `items.data`.
- Not throw on missing entries — if a field is already gone, that's
  a successful no-op, not an error.

## Workflow

The intended operator workflow:

**Install a new lifecycle plugin:**
```bash
composer require acme/products
bin/scriptor plugin:install acme/products
```

**Update a lifecycle plugin:**
```bash
composer update acme/products
# version() string may change; nothing else to do automatically.
# If install() needs to re-run for schema additions, plugin author
# documents that — typically:
bin/scriptor plugin:install acme/products --force
```

**Uninstall a lifecycle plugin:**
```bash
bin/scriptor plugin:uninstall acme/products
composer remove acme/products
```

**Recovery (composer-removed without plugin:uninstall):**
```bash
bin/scriptor plugin:list                       # See the ORPHAN row
bin/scriptor plugin:cleanup-orphan acme/products
# (optionally) clean schema entries manually
```

Composer scripts and Composer plugins are deliberately not used here.
The trade-off was: a `pre-package-uninstall` script could refuse to
let composer remove a not-yet-uninstalled plugin (a "guard"), but
`composer remove --no-scripts` bypasses it, and the friction for the
95% case (operator who follows the workflow) is not worth the 5%
case (operator who skips it anyway). The CLI plus this document are
the contract.

## State file format

`data/plugin-states.json`:

```json
{
    "studenten-frankfurt/katalog": {
        "version": "0.1.0",
        "installed_at": 1748419200
    },
    "acme/products": {
        "version": "1.0.0",
        "installed_at": 1748419500
    }
}
```

- `version`: the composer package version recorded at install time.
- `installed_at`: unix timestamp of the install.

The file lives in `data/`, which is gitignored, so a fresh checkout
starts with no state file. The state manager treats a missing file as
"no lifecycle plugins installed" — `plugin:list` works, and
`plugin:install` creates the file on first call.
