# Hetzner demo deploy — architecture & decisions

Status: **architecture frozen**
Target URL: `https://demos.scriptor-cms.dev`
Host: Hetzner Ubuntu 24.04 box (already provisioned by the operator).

> **Implementation lives at [`bigin/scriptor-cms-ops`](https://github.com/bigin/scriptor-cms-ops)**
> (private). That repo holds the proxy stack, the Scriptor compose
> override, and the runbook (§5–§10 below describe the same files).
> This document is the architecture / decisions reference; keep it in
> sync if the design changes.

---

## 1. Goal & non-goals

### Goal

`https://demos.scriptor-cms.dev` serves the Scriptor 2.0 demo image
(the same one in `docker-compose.yml` at the repo root), with TLS
auto-managed by Let's Encrypt, deployable in one `docker compose up`,
updateable via `git pull && docker compose up -d --build`.

### Non-goals

- Production `scriptor-cms.dev` itself; that comes later, this is
  only the demo subdomain.
- High availability, load balancing, multi-host. One box.
- Database backup automation: covered by a manual cron job /
  rsync recipe, not infra-as-code.
- CI/CD push-to-deploy: manual SSH + git pull is the workflow.

---

## 2. Architecture

```
                  Internet (80/443)
                          │
                          ▼
    ┌─────────────────────────────────────────────────────┐
    │  Hetzner host: UFW (22/80/443), Docker 29.4.3       │
    │                                                      │
    │  ┌────────────────────────┐                         │
    │  │ nginx-proxy            │  ──── Docker socket     │
    │  │ (TLS termination)      │       (read-only)       │
    │  │ ports: 80, 443         │                         │
    │  └─────────┬──────────────┘                         │
    │            │                                         │
    │  ┌─────────▼──────────────┐                         │
    │  │ acme-companion         │  ──── Let's Encrypt     │
    │  │ (issues + renews certs)│       (HTTP-01)         │
    │  └────────────────────────┘                         │
    │                                                      │
    │  ── proxy network (Docker bridge) ──                │
    │            │                                         │
    │  ┌─────────▼──────────────┐                         │
    │  │ scriptor-demo-web      │  ── reads ──┐           │
    │  │ (nginx, port 80)       │             │           │
    │  │ VIRTUAL_HOST=demos…    │             │           │
    │  └────────────┬───────────┘             │           │
    │               │ fastcgi 9000            │           │
    │  ┌────────────▼───────────┐             │           │
    │  │ scriptor-demo          │             │           │
    │  │ (php-fpm)              │             │           │
    │  └────────────┬───────────┘             │           │
    │               │                          │          │
    │   ┌───────────▼──────────┐   ┌───────────▼─────┐   │
    │   │ volume: scriptor-data│   │ volume:         │   │
    │   │ (SQLite, cache, logs,│   │ scriptor-uploads│   │
    │   │  settings)           │   │ (FileStorage)   │   │
    │   └──────────────────────┘   └─────────────────┘   │
    └─────────────────────────────────────────────────────┘
```

Three layers, all in Docker:

1. **Reverse proxy** (`nginxproxy/nginx-proxy`): terminates TLS,
   inspects every other Docker container's `VIRTUAL_HOST` env var,
   generates per-vhost nginx configs on the fly, forwards traffic to
   the matching backend container.

2. **ACME companion** (`nginxproxy/acme-companion`): watches the
   same containers' `LETSENCRYPT_HOST` env, fetches Let's Encrypt
   certs via HTTP-01 challenge (which the proxy answers from
   `/.well-known/acme-challenge/`), stores them in the shared
   `certs` volume, kicks nginx-proxy to reload, schedules renewal.

3. **Scriptor demo stack**: the existing `docker-compose.yml` from
   this repo (php-fpm + nginx siblings sharing the `scriptor-data`
   and `scriptor-uploads` named volumes), with two changes for the
   deploy:
   - drop the `ports: 8080:80` host-publish (nginx-proxy reaches the
     `web` container over the internal `proxy` network)
   - add `VIRTUAL_HOST=demos.scriptor-cms.dev` + `LETSENCRYPT_HOST=…`
     env vars on the `web` container so the proxy + companion pick
     it up

Why nginx-proxy + companion (vs. Caddy / standalone nginx + certbot):
- consistent with the demo's own nginx-based front
- new subdomains = `VIRTUAL_HOST=...` env on the new container,
  zero proxy-config edits
- auto-renewal cron runs in the companion, no host-cron to babysit
- `nginxproxy/*` images are actively maintained, well-documented

---

## 3. Pre-requisites checklist

- [x] Hetzner host: Ubuntu 24.04, Docker 29.4.3 + Compose v5.1.3,
      UFW (22/80/443 open), fail2ban active, sshd hardened, operator
      user in the `docker` group. (Server-setup transcript lives with
      the operator, outside the repo.)
- [x] Domain registered: `scriptor-cms.dev` at IONOS, A record on
      apex pointing to the Hetzner box.
- [ ] **DNS for `demos.scriptor-cms.dev`: USER ACTION (see §4)**.
- [ ] Email for Let's Encrypt notifications: `juri.ehret@gmail.com`
      (assumed, confirm before deploy).

---

## 4. DNS setup at IONOS: USER ACTION

Required BEFORE the first `docker compose up`. acme-companion's
HTTP-01 challenge needs `demos.scriptor-cms.dev` to resolve to the
Hetzner box; otherwise the cert request fails and you get plain HTTP
only.

### Steps in IONOS Control Panel

1. Login → **Domains & SSL** → `scriptor-cms.dev`.
2. **DNS Settings** (Verwaltungs-Tab) → **Add record**:
   - **Type**: `A`
   - **Hostname / Name / Subdomain**: `demos`
   - **Pointing to / IP**: `<Hetzner-IPv4>` (the same IP the apex
     `scriptor-cms.dev` already resolves to; if you're not sure,
     check the existing apex A record).
   - **TTL**: keep the IONOS default (1 hour is fine).
3. (Optional, recommended) **AAAA** record with the Hetzner IPv6 if
   the box has one; saves a fallback round-trip for IPv6 clients.
4. Save.

### Verifying

From your Mac (or anywhere):

```bash
dig +short demos.scriptor-cms.dev
# expected: <Hetzner-IPv4> (and IPv6 if you added AAAA)

# until propagated, you'll see no answer or the previous record.
```

IONOS usually propagates in 5–30 min. If you want faster iteration
during the first deploy, you can lower the TTL to 300 a few minutes
before saving the new record, then bump it back to 3600 once
everything's stable.

---

## 5. Filesystem layout on Hetzner

```
/opt/
├── scriptor-cms-ops/        ← clone of bigin/scriptor-cms-ops
│   ├── proxy/
│   │   └── docker-compose.yml
│   └── scriptor-demo/
│       ├── docker-compose.override.yml
│       ├── .env.example
│       └── .env             ← real values, NOT in git
│
├── proxy/                   ← copy of scriptor-cms-ops/proxy/docker-compose.yml
│   └── docker-compose.yml   (`docker compose up -d` from here)
│
└── scriptor-demo/           ← clone of bigin/Scriptor
    ├── (entire repo contents)
    └── docker-compose.yml   (vanilla local-dev compose, untouched)
```

Three separate Git roots, three separate concerns:

- **Scriptor**: pure app source, safe to `git pull` without ops fallout.
- **scriptor-cms-ops**: host-specific config (proxy stack, override
  file, env values). Private repo because it carries the real domains
  + e-mail.
- **`/opt/proxy/`**: runtime working dir for the proxy. We keep it
  separate from `scriptor-cms-ops/proxy/` so `cd /opt/proxy && docker
  compose ...` is short, and so a re-clone of the ops repo doesn't
  trigger compose to think the project moved (compose tracks the
  containing-dir name).

Both compose stacks share the external Docker network `proxy`.
The proxy stack creates it (`name: proxy` directive); the scriptor
stack joins it as `external: true`.

Why two separate stacks (proxy vs. scriptor):
- proxy is shared infra; could later host other apps under
  different `VIRTUAL_HOST`s without touching the scriptor stack
- updates are independent; `git pull && docker compose up -d` for
  Scriptor doesn't bounce the proxy or its certs
- the bundled `docker-compose.yml` stays unchanged and continues
  to work for local dev; the prod-only bits live in the override
  inside the ops repo

---

## 6. Proxy stack: pinned versions

`nginxproxy/nginx-proxy:1.7` + `nginxproxy/acme-companion:2.5`
on host ports 80/443. Image tag pinning is intentional; it protects
against upstream surprise changes, bump deliberately.

The actual compose file: [`scriptor-cms-ops/proxy/docker-compose.yml`](https://github.com/bigin/scriptor-cms-ops/blob/main/proxy/docker-compose.yml).

---

## 7. Scriptor demo stack override

Env-driven override on top of Scriptor's bundled `docker-compose.yml`.
Lives at [`scriptor-cms-ops/scriptor-demo/docker-compose.override.yml`](https://github.com/bigin/scriptor-cms-ops/blob/main/scriptor-demo/docker-compose.override.yml).

Loaded together with the bundled compose:

```bash
docker compose \
  -f docker-compose.yml \
  -f /opt/scriptor-cms-ops/scriptor-demo/docker-compose.override.yml \
  --env-file /opt/scriptor-cms-ops/scriptor-demo/.env \
  up -d --build
```

Effect on the bundled stack:
- drops `ports: 8080:80` on `web` via the Compose-spec `!reset []`
  marker; otherwise the port lists would merge and 8080 would stay
  published on the host
- adds `VIRTUAL_HOST` + `LETSENCRYPT_HOST` env on `web` so
  nginx-proxy + companion auto-discover and TLS-terminate
- joins the external `proxy` network

Values come from `.env` (`VIRTUAL_HOST=demos.scriptor-cms.dev` +
`LETSENCRYPT_EMAIL=juri.ehret@gmail.com`). The `.env.example` is
checked in as a template; the real `.env` stays out of git.

---

## 8. Initial deploy procedure

Full step-by-step in [`scriptor-cms-ops/README.md`](https://github.com/bigin/scriptor-cms-ops/blob/main/README.md#initial-deploy).
Pre-flight from your Mac: `dig +short demos.scriptor-cms.dev` must
return the Hetzner IPv4 before the first `docker compose up`,
otherwise acme-companion's HTTP-01 challenge will fail.

---

## 9. Updates

App code:

```bash
ssh hetzner
cd /opt/scriptor-demo
git pull
docker compose \
  -f docker-compose.yml \
  -f /opt/scriptor-cms-ops/scriptor-demo/docker-compose.override.yml \
  --env-file /opt/scriptor-cms-ops/scriptor-demo/.env \
  up -d --build
```

Override / env tweaks:

```bash
ssh hetzner
cd /opt/scriptor-cms-ops && git pull
# rerun the compose up -d above to apply
```

Named volumes (`scriptor-data`, `scriptor-uploads`) survive container
recreation, so the DB and uploads persist across updates.

Proxy stack at `/opt/proxy` is independent; only update when bumping
the pinned versions.

---

## 10. Backups

Manual, demo-shaped (this is a demo, not production data):

```bash
ssh hetzner
cd /opt/scriptor-demo
docker compose exec scriptor sh -c \
  'cp /var/www/scriptor/data/imanager.db /tmp/imanager.db.bak'
docker cp scriptor-demo:/tmp/imanager.db.bak \
  /opt/scriptor-demo-backups/imanager.db.$(date +%F).bak
```

Uploads survive in their named volume; back up `public/uploads/`
the same way if needed (compose project on Hetzner is `scriptor-demo`,
so the volume on disk is `scriptor-demo_scriptor-uploads`):

```bash
docker run --rm \
  -v scriptor-demo_scriptor-uploads:/uploads:ro \
  -v /opt/scriptor-demo-backups:/bk \
  alpine tar czf /bk/uploads.$(date +%F).tar.gz -C / uploads
```

For the demo, "if it breaks, blow it away and reseed" is also a
valid posture; `docker compose down -v` reseeds on next `up`.

---

## 11. Smoke matrix (post-deploy)

Same as the public/-webroot test matrix (`docs/refactor-public-webroot.md` §7),
just against `https://demos.scriptor-cms.dev` instead of localhost.

| Path                                          | Expected |
|-----------------------------------------------|----------|
| `https://demos.scriptor-cms.dev/`             | 200, frontend renders |
| `https://demos.scriptor-cms.dev/editor/`      | 200, login form |
| `https://demos.scriptor-cms.dev/themes/basic/css/styles.css` | 200 |
| `https://demos.scriptor-cms.dev/data/imanager.db`            | 404 |
| `https://demos.scriptor-cms.dev/boot/App.php`                | 404 |
| `https://demos.scriptor-cms.dev/.htaccess`                   | 404 (nginx-proxy + the demo image's nginx.conf both block dotfiles) |
| `http://demos.scriptor-cms.dev/`              | 301 → https |
| TLS cert subject                              | `CN=demos.scriptor-cms.dev` (Let's Encrypt) |

Cert detail:

```bash
echo | openssl s_client -servername demos.scriptor-cms.dev -connect demos.scriptor-cms.dev:443 2>/dev/null \
    | openssl x509 -noout -subject -issuer -dates
```

Login: `admin` / `gT5nLazzyBob` (baked seed in `docker/seed-demo.sql`).

---

## 12. Rollback

See [`scriptor-cms-ops/README.md` § Rollback](https://github.com/bigin/scriptor-cms-ops/blob/main/README.md#rollback)
for the exact commands. In short: `down` drops Scriptor but keeps the
volume (and the proxy + certs); `down -v` also drops the named volume,
re-seeding the DB on next `up`.

If a Let's Encrypt rate limit or HTTP-01 challenge fails, the cert
issuance retries automatically (acme-companion polls every hour by
default). Watch `docker logs nginx-proxy-acme`.

---

## 13. Open / future

Things explicitly out of scope for this plan but worth a follow-up:

1. **Image distribution**: today the deploy clones the repo on the
   box and builds the image there. Faster: push `bigins/scriptor-demo`
   to GHCR/Docker Hub and the deploy compose just `image:`-pulls.
2. **Centralised logs**: nginx-proxy + acme + scriptor each have
   their own `docker logs`. A simple `docker compose logs -f`
   covers daily ops; later move to vector / promtail / etc. if the
   number of subdomains grows.
3. **Monitoring**: nothing pages anyone today. `uptimerobot`
   pinging `https://demos.scriptor-cms.dev/` is the smallest viable
   add when ready.
4. **Production `scriptor-cms.dev`**: when ready, the same recipe
   adds a second container with `VIRTUAL_HOST=scriptor-cms.dev`.
   The proxy stack handles both transparently.

---

## 14. Decisions log

- **TLS via Let's Encrypt + acme-companion** (not the IONOS
  wildcard cert). Reason: auto-renewal, no manual yearly re-import,
  per-host cert keeps subdomains independent. The IONOS private
  key stays with the operator as an offline fallback.
- **nginx-proxy + acme-companion** (not Caddy). Reason: matches
  the demo image's own nginx, ≤ ~6 subdomains expected in 12
  months, well within easy operational reach.
- **Two separate compose stacks** (not one big file). Reason:
  proxy is shared infra, scriptor is one of (potentially) many
  apps later; independent lifecycles.
- **Separate ops repo (`bigin/scriptor-cms-ops`)** for the override
  + proxy stack + .env, instead of putting them in Scriptor itself.
  Reason: keep host-specific values (domain, e-mail) and
  shared-infra config out of the open-source app source; gives
  future apps (`scriptor-cms.dev` prod, etc.) a natural home;
  Scriptor stays a clean "the application", not "the application +
  my deployment".
- **`!reset []` to drop ports in the override** (not a base-file
  edit). Reason: keep `docker-compose.yml` working for local-dev;
  prod-only behavior lives in the override.
- **Manual `git pull`-based updates** (not push-to-deploy). Reason:
  one operator (juri), low change frequency, easier to reason about.

---

## 15. Sequence summary

```
1. Read this doc, agree or amend.
2. USER ACTION: add `demos A <hetzner-ip>` at IONOS, verify with dig.
3. Bootstrap scriptor-cms-ops repo with proxy stack + override + README.
4. SSH hetzner, follow scriptor-cms-ops/README.md § Initial deploy.
5. Verify §11 smoke matrix here.
```
