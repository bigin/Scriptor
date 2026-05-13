# Changelog

## 2.0.0 — Ground-up rewrite on iManager 2.0

Scriptor 2.0 replaces the embedded 1.x `imanager/` library with the
external [iManager 2.0][imanager] package and rebuilds Frontend +
Editor + Upload pipeline on PSR-standards (PSR-3 logging, PSR-14 event
dispatch, PSR-16 caching). The legacy 1.x flat-file storage is gone.

### Added

- **SQLite storage** with JSON columns and FTS5 full-text search.
- **Composer-based** install (`bigins/imanager:^2.0` is the only direct
  runtime dep besides Symfony Console for the CLI).
- **Domain-event listeners** (`Scriptor\Boot\Events\*`):
  - `ItemFileCleanupListener` — drops uploaded files (and thumbnails)
    from disk when an item is deleted, before the FK cascade clears
    the metadata rows.
  - `PageCacheInvalidationListener` — flushes the rendered-page cache
    on every Pages-category mutation.
- **FilePond** uploads with on-demand thumbnail generation through
  `intervention/image`. Endpoint: `/editor/api/upload` (POST/PATCH/DELETE).
- **Per-image titles** as a typed `files.title` column (iManager 2.0
  schema migration `0004`). Captions render through `Sanitizer::markdown`
  on the frontend so links and emphasis work in 2.0 the same way they
  did in 1.x.
- **Single-entry routing** — root `index.php` delegates `/<admin_path>/*`
  to `editor/index.php` at the PHP level, which works on Apache, Caddy,
  Nginx, and PHP's built-in server without per-server rewrite rules.
- **`bin/perf-smoke.php`** runs Plan §8.2 timing checkpoints against
  the live SQLite database.
- **`Scriptor\Boot\Editor\*`** rewrites of all admin modules (auth,
  pages, profile, settings, install) on the iManager 2.0 stack.
- **`Scriptor\Boot\Frontend\*`** — `Site`, `Page`, `PageRepository`,
  `Sanitizer`, `ImageUrlBuilder` — public-site renderer that bundled
  themes consume through the standard `$site` surface.

### Fixed

- **Pages tree: indirect-cycle guard on parent save.**
  `PageRepository::wouldCreateCycle()` walks the proposed parent's
  existing chain upwards; if it reaches the page being edited,
  `PagesModule::saveAction()` refuses the save with a localised
  error (`error_page_parent_cycle`, en + de). The direct
  self-parent case was already collapsed to root, but indirect
  cycles (a → b → c → a) could be saved silently. The frontend's
  `Site::buildPageUrl()` already tolerates cyclic data via a
  visited-set guard, so the editor now matches the frontend's
  defensive shape.

### Removed

- `Scriptor/imanager/` — the entire embedded 1.x library (~850 KB).
- `data/datasets/buffers/` — flat-file storage; superseded by SQLite.
- `editor/core/` — legacy `Scriptor\Core\Scriptor`, `Module`, `Site`,
  `Pages`, `User`, `Editor`, `CSRF`, `Helper`. All replaced by
  `Scriptor\Boot\*` equivalents.
- `editor/modules/` — every legacy admin module file. Replacements live
  under `boot/Editor/Auth/`, `boot/Editor/Pages/`, `boot/Editor/Profile/`,
  `boot/Editor/Settings/`, `boot/Editor/Install/`.
- `imanager.php` (root file) — old `imanager()`-bootstrap stub.
- The legacy `Scriptor::execHook()` system. Domain events on the
  iManager side replace it; if a 3rd-party module needs the old hook
  shape we can ship a Hook-Bridge listener provider in a follow-up.

### Migration from 1.x

`vendor/bin/imanager migrate:from-v1 --source data --target data/imanager.db`
performs a one-shot import. See [README.md](README.md#migrating-from-1x).
The command takes a `--dry-run` flag for previewing the import.

### Performance

Plan §8.2 budgets and typical results on the bundled demo data:

| Operation                          | Result    | Budget    | Headroom |
|------------------------------------|-----------|-----------|----------|
| `items()->find($id)`               | 0.009 ms  | 1.0 ms    | 110×     |
| `findByCategory(pages, 0, 20)`     | 0.025 ms  | 50.0 ms   | 2 000×   |
| `FullTextSearch::search`           | 0.037 ms  | 100.0 ms  | 2 700×   |

[imanager]: https://github.com/bigin/imanager

---

## 1.x

Pre-2.0 history lives on the `1.x-final` tag (created at the
imanager-2.0 cutover). The 1.x branch was a flat-file CMS with an
embedded `imanager/` library shipping its own `Imanager\ItemManager`,
`FieldFileupload`, `TemplateParser`, etc. See the git log on
`1.x-final` for the per-version detail.
