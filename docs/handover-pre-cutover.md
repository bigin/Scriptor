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

> 🟡 Bigin will populate this list in the next conversation turn.
> Add an entry per issue with: shape of the bug, repro steps, where
> in the code I think the fix lives, and which sub-PR pattern fits
> (`fix/<slug>` off `imanager-2.0`).

(Section deliberately empty — fill in as Bigin reports.)

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
