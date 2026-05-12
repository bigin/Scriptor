# Pre-Cutover Handover — Scriptor 2.0

> **Read this first** if you're picking the project up after a context
> reset (e.g. after `/compact`). It's the canonical "where are we
> and what comes next" document.

## TL;DR

- **Phase 14 is feature-complete on `imanager-2.0`.** Fourteen sub-phases
  plus inline hotfixes plus a post-14f image-titles feature.
- **The cutover is NOT yet done.** `master` is still 1.x, `imanager-2.0`
  is the integration branch.
- **Acceptance issues block the cutover.** Bigin will list them in the
  next conversation turn — do **not** start the cutover until the list
  is resolved and Bigin says "go".
- **Live site:** `https://scriptor.cms` on ServBay (Caddy + PHP-FPM).
- **Live database:** `data/imanager.db`, schema v4 (initial + fts +
  files + files_title).
- **iManager dependency:** path repo at `../imanager`, dev-main branch
  alias `2.0.x-dev`. Packagist release lives in Phase 17.

## Where the work shipped

Master plan: `../imanager/docs/imanager-2.0-plan.md` (Phase 14 ✅).
Detail plan: `../imanager/docs/imanager-2.0-phase-14-plan.md` (all
sub-phases ✅ with Scriptor PR numbers).

| Phase 14 sub-phase                      | Scriptor PR |
|-----------------------------------------|-------------|
| 14a bootstrap                           | #18 |
| 14b-1 frontend skeleton                 | #19 |
| 14b-2 BasicTheme rewrite                | #21 |
| 14b-3 image pipeline                    | #22 |
| 14c-1 auth                              | #20 |
| 14c-2 pages                             | #24 |
| 14c-3 profile (collapses 14c-3 + 14c-6) | #26 |
| 14c-4 settings                          | #27 |
| 14c-5 install                           | #28 |
| 14d-1 upload endpoint + FilePond vendor | #29 |
| 14d-2 pages-uploads (FilePond on form)  | #30 |
| 14d-3 frontend renders FileRepository   | #31 |
| 14e-2 listeners (cleanup + cache)       | #32 |
| 14f cleanup + perf-smoke + docs         | #36 |
| post-14f image titles                   | (image-titles + iManager `feature/file-title-column`) |

iManager-side companions on `main`: `phase-14e1-events`,
`feature/file-title-column`, `docs/phase-14-done` (latest).

## Pre-cutover acceptance issues

Issues surfaced during the pre-cutover testing window. Resolved
entries have landed on `imanager-2.0` via the listed branches.

### ✅ Resolved on `imanager-2.0`

1. **v1→v2 parent ID mapping (data + theme config).**
   The `migrate:from-v1` CLI re-numbered page IDs but kept the
   literal v1 `parent` values inside JSON `items.data`. On this
   install three Footer-Pages children carried `parent=8` (v1 id
   of "Footer Pages"); v2 id 8 is "Getting Help", v2 id 6 is the
   actual "Footer Pages" — leaving page 8 as a self-parent. Same
   class of mismapping hit the theme config (`footer_container_id`,
   `main_nav_exclude_ids`).
   - Data fix: manual `UPDATE items SET data = json_set(data,
     '$.parent', 6) WHERE id IN (5,7,8)`; snapshot in
     `data/imanager.db.pre-parent-mapping-fix`.
   - Config fix: `fix/post-migration-config` (`footer_container_id`
     8→6, drop obsolete `8` from `main_nav_exclude_ids`).
   - The mapping bug itself **still lives in iManager's
     `migrate:from-v1` CLI** and will hit any other 1.x install —
     see Deferred §1.

2. **Editor UI refresh (Design System + Pages styling + theme fixes).**
   Adopted Scriptor Design System tokens (`tokens.css`), redesigned
   the editor sidebar nav, primary-button variant. Pages-edit image
   section: horizontal layout, button-spacing, hover highlight.
   Editor messages list got success/error styling. Build chore:
   served `styles.css` directly (dropped `styles.min.css`). Theme
   bugfixes: cache no longer double-renders on hit; theme-config
   correctly overrides scriptor-config (precedence was reversed).
   - Branch: `feat/editor-ui-refresh`.

3. **Page-list table header readability.**
   `<th>` used `--color-surface-alt`, identical to the zebra-stripe
   even-row background — header visually merged with rows. Switched
   to `--color-brand` semibold uppercase label on transparent ground,
   2 px brand-divider bottom-border; removed the redundant `thead tr`
   top-border.
   - Branch: `feat/editor-table-header`.

4. **Pages-list parent column showed bare ID.**
   `(string) $page->parent` forced a mental lookup. Replaced with
   `<Name> (<linked-ID>)` using an O(1) id→page map for resolution;
   orphan parents fall back to bare `(ID)`. Only the ID itself is
   anchored so the column stays readable.
   - Branch: `feat/editor-pages-parent-label`.

### ⏳ Deferred (post-cutover, low priority)

1. **iManager `migrate:from-v1` does not remap `parent` after
   ID-renumber.** Same root cause as Resolved §1; any future v1→v2
   migration will reproduce the same self-parent / wrong-parent
   pattern. Fix lives in `Imanager\Cli\Migrate\FromV1Command` (or
   wherever the post-write renumber pass is — needs locating).
   Suggested sub-PR pattern: `fix/migrate-from-v1-parent-remap` off
   `bigins/imanager` `main`.

2. **Cycle-guard in Scriptor's page save-flow.**
   `PagesModule.php:115-117` rejects the exact-self case (parent ==
   self), and `renderParentOptions()` does not offer self in the
   dropdown. Indirect cycles (`a→b→a`) are not detected — the
   dropdown today still allows picking a descendant as parent.
   Realistic in practice only via crafted POSTs since the dropdown
   omits descendants when the API is consistent, but a
   `wouldCreateCycle()` server-side check is the durable answer.
   Suggested sub-PR pattern: `fix/pages-cycle-guard` off
   `imanager-2.0` (or post-cutover, off `master`).

## Cutover workflow (Plan §3)

**DESTRUCTIVE — only run on Bigin's explicit "go".**

The plan mandates renaming `imanager-2.0` → `master`, archiving the
old `master` as a tag `1.x-final`, and keeping a moment to verify the
GitHub default-branch follow-up.

```bash
cd /Applications/ServBay/www/Scriptor

# 1. Make sure you're up to date and clean
git fetch origin --prune
git status     # must be clean
git checkout imanager-2.0
git pull --ff-only origin imanager-2.0

# 2. Tag the old master so 1.x history stays reachable
git fetch origin master:master
git tag 1.x-final master
git push origin 1.x-final

# 3. Promote imanager-2.0 to master locally
git branch -m master old-master       # rename old local
git branch -m imanager-2.0 master     # rename current local

# 4. Push the new master and remove the integration branch on the remote.
#    --force-with-lease is the safety net — abort if someone pushed
#    to origin/master while we were renaming.
git push --force-with-lease origin master
git push origin :imanager-2.0
git push origin :old-master            # cleanup; optional

# 5. Update GitHub default branch (UI — not a CLI step):
#    Settings → Branches → Default branch → master.
#    Re-target any open PRs that still pointed at imanager-2.0.

# 6. Refresh local tracking on the new branch
git branch --set-upstream-to=origin/master master
```

After the cutover:

- The `imanager-2.0` branch is gone everywhere.
- All previously merged PRs still resolve through their commit SHAs;
  GitHub's "Merged into imanager-2.0" badges keep working.
- Anyone with a clone needs:
  ```bash
  git fetch origin --prune
  git checkout master
  git reset --hard origin/master
  ```

## What happens after the cutover

- **Phase 16 (iManager) — Docs & Examples.** Quickstart,
  example-app skeleton, ADRs for the bigger 2.0 design choices.
- **Phase 17 (iManager) — 2.0.0 release.** Tag on Packagist; Scriptor
  switches `composer.json` from path-repo to `^2.0`.
- **Demo site rebuild** at `demos.scriptor-cms.info` once 17 ships.

## Pointers (for the agent picking this up)

- Memory index: `~/.claude/projects/-Applications-ServBay-www-Scriptor/memory/MEMORY.md`
  with profile, workflow, project status, and reference repos.
- Repo working trees: `/Applications/ServBay/www/Scriptor` and
  `/Applications/ServBay/www/imanager`.
- Smoke pattern: `php -S 127.0.0.1:<port>` + `curl` with a cookie jar
  for editor flows; `vendor/bin/imanager schema:migrate --db=…` for DB
  upkeep; `php bin/perf-smoke.php` for performance verification.
- Branching pattern: every Scriptor change goes onto a fresh branch
  off `imanager-2.0` and PRs back into it. Hotfixes live on
  `fix/<slug>` branches and are merged before the in-flight feature
  PR when relevant.
