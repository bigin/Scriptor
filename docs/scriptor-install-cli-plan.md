# Scriptor Install CLI: design plan

Status: **plan drafted, awaiting review**
Driver: a bare `git clone` + `composer install` does not produce a
working Scriptor install today. The schema is auto-created on first
boot (iManager's `DefaultBootstrap` runs `SchemaManager::migrate()`),
but no rows are inserted, so the first frontend request throws:

```
RuntimeException: Category with slug "pages" not found in the
iManager database
  at boot/Frontend/PageRepository.php:34
```

The Pages category, its fields, and the admin user only exist
inside `docker/seed-demo.sql`, which the Docker entrypoint replays on
first start. Outside Docker, there is no documented or supported
path. This plan introduces `bin/scriptor install`, a CLI-only,
idempotent initial-setup command, and retires the SQL-dump-as-seed
pattern in favour of a single source of truth.

> Implementation lives in this repo under
> [`boot/Cli/`](../boot/Cli/) (new) plus the `bin/scriptor` entry
> point. iManager remains untouched: the install command consumes
> existing iManager primitives (`CategoryRepository::ensure()`,
> `FieldRepository::ensure()`, `ItemRepository::save()`) rather than
> adding Scriptor-specific knowledge to the library.

---

## 1. Goal and non-goals

### Goal

A first-time Scriptor operator can run:

```bash
git clone git@github.com:bigin/Scriptor.git
cd Scriptor
composer install
php bin/scriptor install
```

and end up with a working `/` plus a working `/editor/auth/`,
without copying SQL files around. The install command must be safe
to run on an existing install (refuses to overwrite), must not be
reachable over HTTP, and must not ship a default admin password.

### Non-goals

- **Schema migrations.** iManager owns its schema and already auto-
  applies migrations during boot (`DefaultBootstrap::boot()` →
  `SchemaManager::migrate()`). `bin/scriptor install` only seeds
  *data*; it does not touch DDL.
- **A web-based setup wizard.** No `public/install.php`, no first-
  run modal in `/editor/`. CLI only. Cf. §4.
- **Multi-tenant or per-site config.** Single Scriptor install per
  DB, as today.
- **Plugin-managed install steps.** Plugins do not get a setup hook
  in this PR. If a plugin needs setup, it ships its own CLI.
- **Reinstall / reset.** Out of scope for v1. To reset, the
  operator deletes `data/imanager.db` and re-runs the command.

---

## 2. Current state and root cause

| Path | Today | Outcome |
|---|---|---|
| `composer install` + browser | iManager auto-migrates schema only | 500 on `/` (missing Pages category), no admin user, login impossible |
| `docker compose up -d --build` | `docker/entrypoint.sh` runs `sqlite3 < docker/seed-demo.sql` if no DB exists | Pages + Users + admin user + 1 example Home page, demo password `gT5nLazzyBob` |
| Shared hosting (`docs/install-shared-hosting.md`) | Same as composer install path, no seed step documented | Same 500 |

The seed dump is a working snapshot but has three problems:

1. It encodes Scriptor's data shape in `.sql` instead of code, so
   schema drift between dump and live install is invisible until a
   migration breaks.
2. It hard-codes a known admin password, fine for the Docker demo,
   dangerous if any operator runs the same SQL on a public host.
3. There is no non-Docker setup path. The Build-a-Theme tutorial
   (Phase D) implicitly assumes a running Scriptor install, but
   the README does not say how to get one without Docker.

---

## 3. Architecture decision: Scriptor-side CLI, iManager-side primitives

### Why not an `imanager bootstrap:initial` command

iManager is a library. It must not know about Scriptor's `Pages`
category convention, the `slug/parent/pagetype/menu_title/...`
field shape, or what an admin user looks like in this app. Putting
that knowledge into iManager violates the one-way dependency
(`Scriptor → iManager`) the project deliberately maintains.

Anything iManager could legitimately do here is generic ("create a
category with these N fields"), and iManager already exposes that
through `CategoryRepository::ensure()` / `FieldRepository::ensure()`
since 2.1.0. The Scriptor-shaped composition belongs in Scriptor.

### What `bin/scriptor install` does

1. Connect to the same iManager container the request lifecycle
   uses (`ImanagerBootstrap::create(__DIR__)`), so the same
   migrations run that a request would have run.
2. Refuse to proceed if `findBySlug('pages')` returns non-null
   (already installed, abort with a clear message). Cf. §4.
3. `ensure()` the **Pages** category + its 9 fields, exactly the
   set in `docker/seed-demo.sql` so the editor's existing forms keep
   working without changes:

   `slug, parent, pagetype, menu_title, content, template, images, summary, position`

4. `ensure()` the **Users** category + its 4 fields:
   `username, email, password, role`.
5. Read the admin password from one of three sources (precedence
   in order):
   - `--password=...` CLI flag,
   - `SCRIPTOR_ADMIN_PASSWORD` environment variable,
   - Interactive prompt (TTY-only) with confirmation.

   Reject passwords shorter than 12 characters or in a small
   blacklist (`admin`, `password`, `gT5nLazzyBob`).
6. Hash via `password_hash($password, PASSWORD_BCRYPT)` and store
   as `Imanager\PasswordFieldValue` JSON, the shape the live editor
   already reads.
7. Create a minimal Home page: `slug=home`, `template=home`,
   `position=1`, `name=Home`, `content=<welcome blurb>`. This makes
   `/` render immediately so a first-time user sees the site work,
   rather than a 404 on an empty page tree.
8. Print a summary block: the admin username, the editor URL, and
   a reminder that the password was not echoed.

---

## 4. Security guarantees

Auto-installers have historically been a soft spot in PHP CMS
ecosystems. Two failure modes account for most of the well-known
incidents:

- **Web-reachable installer that does not lock itself** after first
  run. Attacker triggers a second run, overwriting admin
  credentials or the database.
- **Default credentials** in the seed (`admin/admin`,
  `admin/password`, …) that the operator forgot to change before
  the box was indexed.

The plan addresses both, plus four secondary patterns.

| Rule | Mechanism in code |
|---|---|
| **G1. CLI only.** The install command never executes from a request. | First statement of the command: `if (\PHP_SAPI !== 'cli') { exit(1); }`. Belt-and-suspenders: `bin/scriptor` is outside `public/`, so the webroot does not expose it even if `\PHP_SAPI` were spoofed. |
| **G2. Idempotent via real-state check, not a lock-file.** A re-run never overwrites. | `CategoryRepository::findBySlug('pages')` is the gate. If it returns non-null, the command aborts with: "Scriptor already installed. To start over, delete data/imanager.db explicitly." No `.installed` marker (which an attacker could remove). |
| **G3. No default password.** | Password is mandatory, read from flag / env / TTY prompt. There is no fallback. |
| **G4. Password complexity baseline.** | Minimum length 12. Hard-coded blacklist of the obvious defaults including this project's old demo password. Rejected with a clear error. |
| **G5. Explicit confirmation step.** | TTY-only prompt: "About to seed `<absolute DB path>`. Type INSTALL to proceed." Prevents reflex `<Enter>` on the wrong shell. The `--yes` flag is the only way to skip this, for CI / Docker. |
| **G6. Single seed for both paths.** | The Docker entrypoint shells to `bin/scriptor install` with `--password "$SCRIPTOR_ADMIN_PASSWORD"` and `--yes`. `docker/seed-demo.sql` is retired in a follow-up PR. One code path = one audit surface. |

### What this does **not** protect against

- Shell access on the host. If an attacker has shell access, they
  can read the DB directly; install is not the weakest link.
- Plain HTTP between operator and the box during password prompt.
  Out of scope; the operator should run the command locally on the
  host, not over an untrusted SSH session. (Though SSH itself is
  encrypted, the message is: do not paste passwords through brittle
  channels.)
- Compromised composer dependencies. Lock-file pinning is the
  defence there, not this CLI.

---

## 5. CLI surface

### Synopsis

```
php bin/scriptor install [options]

Options:
  --password=<value>    Admin password. Overrides SCRIPTOR_ADMIN_PASSWORD env.
  --username=<name>     Admin username (default: admin).
  --email=<addr>        Admin email (default: admin@localhost).
  --yes                 Skip the "type INSTALL to proceed" confirmation.
                        Required when stdin is not a TTY (CI, Docker).
  --db=<path>           Override database path (default: data/imanager.db).
  --help                Print this message.
```

### Exit codes

- `0` success
- `1` already installed (G2)
- `2` invalid password (G3 / G4)
- `3` non-CLI invocation rejected (G1)
- `4` unexpected error (any uncaught exception during seed)

### Output sample

```
$ php bin/scriptor install
About to seed /var/www/Scriptor/data/imanager.db.
Type INSTALL to proceed: INSTALL
Enter admin password (12+ chars):
Confirm admin password:
[1/4] Pages category + 9 fields created.
[2/4] Users category + 4 fields created.
[3/4] Admin user 'admin' created.
[4/4] Home page seeded (slug=home, template=home).

Scriptor is ready.
  Frontend:    http://your-host/
  Editor:      http://your-host/editor/
  Admin user:  admin
```

### Future commands (out of scope for v1)

`bin/scriptor` is structured to grow more subcommands later:
`bin/scriptor user:add`, `bin/scriptor user:passwd`,
`bin/scriptor backup`, `bin/scriptor cache:clear`. The dispatcher
is a simple switch in `bin/scriptor`; no Symfony Console dependency
is added until the surface justifies it.

---

## 6. Implementation outline

### Files added

| Path | Purpose |
|---|---|
| `bin/scriptor` | Executable PHP entry. Argv parsing, subcommand dispatch, exit codes. ~80 lines. |
| `boot/Cli/InstallCommand.php` | The actual install logic. Pure PHP class, no Symfony Console. ~250 lines. |
| `boot/Cli/PasswordPrompt.php` | TTY password reader (`stty -echo`), confirmation, blacklist check. ~80 lines. |
| `tests/Cli/InstallCommandTest.php` | Unit + integration tests (cf. §8). |
| `docs/install.md` | New: setup walkthrough (composer install + `bin/scriptor install`). README links here. |

### Files changed

| Path | Change |
|---|---|
| `README.md` | Add the `php bin/scriptor install` step in the Installation section. Drop the misleading "creates the DB on first request" sentence; replace with a pointer to `docs/install.md`. |
| `composer.json` | Add `"bin": ["bin/scriptor"]`. Lets Composer mark it executable when Scriptor is consumed as a Composer package. |
| `.gitignore` | No change. `data/imanager.db` already ignored. |

### Files retired in a follow-up PR (not this one)

| Path | Reason |
|---|---|
| `docker/seed-demo.sql` | Replaced by `bin/scriptor install --yes --password=$SCRIPTOR_ADMIN_PASSWORD` in the entrypoint. |
| `docker/seed-demo-uploads.tar.gz` | Same. The uploads tarball stays only as long as the Docker demo wants to ship example images; if it does, the entrypoint runs a separate `bin/scriptor demo:seed-uploads` step (designed but out of scope here). |
| `docker/entrypoint.sh` (seed branch only) | Re-pointed at the CLI. |

The retirement PR ships after the install CLI is merged + smoked in
the demo container, so we never leave the Docker setup in a
half-migrated state.

---

## 7. Why this is one PR, not two

Tempting to split into "v1: Scriptor CLI seeds data" and "v2: Docker
entrypoint uses CLI". But:

- The Docker entrypoint is the only existing call site of the seed
  artefact. Leaving `docker/seed-demo.sql` alive after the CLI lands
  means schema drift can still hide there.
- Tests for the install command (§8) exercise the same surface the
  Docker entrypoint hits, so retiring the SQL has no extra QA cost.

That said, the **retirement of the SQL dump is a follow-up PR**, not
part of the install-CLI PR itself. Splitting on that boundary
because the dump change touches the demo image, which has its own
smoke + tag cycle.

---

## 8. Test plan

### Unit tests

- `InstallCommand` refuses non-CLI invocation (mocks `\PHP_SAPI`).
- `InstallCommand` refuses to run when Pages category exists.
- `InstallCommand` rejects passwords < 12 chars.
- `InstallCommand` rejects blacklisted passwords (`gT5nLazzyBob`,
  `admin`, …).
- `PasswordPrompt` reads from `--password`, falls back to env, then
  to TTY prompt; rejects mismatched confirmation.
- Successful run creates Pages with 9 fields, Users with 4 fields,
  1 admin user, 1 Home page. Field config matches `seed-demo.sql`
  byte for byte where applicable.

### Integration test

A new test under `tests/Cli/InstallCommandIntegrationTest.php`:

1. Boots a fresh container against a temp SQLite file.
2. Runs `InstallCommand` programmatically with a fixed password.
3. Boots a `Site` instance against the same DB. Verifies
   `PageRepository` resolves Home, `findActiveByParent(0)` returns
   one page, and a synthetic login attempt against the seeded admin
   credentials succeeds via iManager's authentication path.

### Manual smoke after merge

| Step | Expected |
|---|---|
| Fresh clone of Scriptor, `composer install`, `php bin/scriptor install` (interactive). | Pages + Users + admin + Home created; `/` returns 200 with "Home" rendered. |
| Re-run the command on the same DB. | Exits with code 1 and the "already installed" message. No DB mutation. |
| `php bin/scriptor install --password=short`. | Exits with code 2, error printed. |
| `cat foo.txt \| php bin/scriptor install` (no TTY, no `--yes`). | Exits with code 4 (cannot confirm), prints hint about `--yes`. |
| `docker compose up -d --build` (after the follow-up PR). | Container boots, demo seed runs through the CLI, the same login works. |

---

## 9. Open questions

These are explicitly **deferred** unless a reviewer pushes back:

1. **Symfony Console vs. hand-rolled dispatch.** Today's iManager
   CLI uses Symfony Console. Adding it as a Scriptor dependency for
   one command feels heavy; the hand-rolled switch in `bin/scriptor`
   stays simple until the subcommand list grows past three or four.
   Revisit when `user:add` and `cache:clear` land.
2. **Should `bin/scriptor install` also accept a config file** like
   `install.yml` so a CI pipeline can declaratively describe the
   target state? Postpone; the env-var / flag pattern is enough for
   the use cases we have today, and YAML adds a parsing surface.
3. **What happens on the shared-hosting setup** where the operator
   cannot run a shell command on the box? `docs/install-shared-hosting.md`
   currently assumes the operator can at least upload + curl-trigger
   a script. The install CLI itself works fine from a CGI/CLI hybrid
   if the host allows `php bin/scriptor install` through their SSH
   panel. If they cannot, we revisit a web installer with the same
   security guarantees applied (lock-file + IP-token + delete-after-run).
   Marking out of scope here.
4. **i18n for prompts.** All prompt strings are English in v1.
   Editor language strings live under `editor/lang/*.php`; the
   install CLI does not pull from there yet. Trivial to add later
   if needed.

---

## 10. PR sequence

| PR | Title | Scope |
|---|---|---|
| 1 (this) | `docs(plan): scriptor install CLI design` | This document only. |
| 2 | `feat(cli): bin/scriptor install + InstallCommand` | All of §6 except the Docker entrypoint. |
| 3 | `chore(docker): seed via bin/scriptor instead of seed-demo.sql` | Switch entrypoint, retire SQL dump, retag demo image. |
| 4 | `docs(install): rewrite install.md + README pointer` | If §6's README/docs change has not landed in PR 2; otherwise skip. |

PR 2 is the load-bearing one; 3 and 4 are mechanical follow-ups.
