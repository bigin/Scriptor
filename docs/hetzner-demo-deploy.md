# Hetzner demo deploy — plan

Status: **plan, not yet implemented**
Target URL: `https://demos.scriptor-cms.dev`
Host: Hetzner Ubuntu 24.04 box (already provisioned — see project memory)

This document is the single source of truth for getting Scriptor's
demo image onto the Hetzner box behind nginx-proxy + Let's Encrypt.
Update it as decisions land.

---

## 1. Goal & non-goals

### Goal

`https://demos.scriptor-cms.dev` serves the Scriptor 2.0 demo image
(the same one in `docker-compose.yml` at the repo root), with TLS
auto-managed by Let's Encrypt, deployable in one `docker compose up`,
updateable via `git pull && docker compose up -d --build`.

### Non-goals

- Production `scriptor-cms.dev` itself — that comes later, this is
  only the demo subdomain.
- High availability, load balancing, multi-host. One box.
- Database backup automation — covered by a manual cron job /
  rsync recipe, not infra-as-code.
- CI/CD push-to-deploy — manual SSH + git pull is the workflow.

---

## 2. Architecture

```
                  Internet (80/443)
                          │
                          ▼
    ┌─────────────────────────────────────────────────────┐
    │  Hetzner host — UFW (22/80/443), Docker 29.4.3      │
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
    │   ┌───────────▼─────────────────────────▼─────┐    │
    │   │ named volume: scriptor-app                │    │
    │   │ (the entire repo + data + uploads)        │    │
    │   └────────────────────────────────────────────┘   │
    └─────────────────────────────────────────────────────┘
```

Three layers, all in Docker:

1. **Reverse proxy** (`nginxproxy/nginx-proxy`) — terminates TLS,
   inspects every other Docker container's `VIRTUAL_HOST` env var,
   generates per-vhost nginx configs on the fly, forwards traffic to
   the matching backend container.

2. **ACME companion** (`nginxproxy/acme-companion`) — watches the
   same containers' `LETSENCRYPT_HOST` env, fetches Let's Encrypt
   certs via HTTP-01 challenge (which the proxy answers from
   `/.well-known/acme-challenge/`), stores them in the shared
   `certs` volume, kicks nginx-proxy to reload, schedules renewal.

3. **Scriptor demo stack** — the existing `docker-compose.yml` from
   this repo (php-fpm + nginx siblings sharing the `scriptor-app`
   volume), with two changes for the deploy:
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
      UFW (22/80/443 open), fail2ban active, sshd hardened, user
      `juri` in the `docker` group. (Server-setup transcript:
      `/Users/juri/Documents/HETZNER-IONOS/Claude-Servereinrichtung.txt`.)
- [x] Domain registered: `scriptor-cms.dev` at IONOS, A record on
      apex pointing to the Hetzner box.
- [ ] **DNS for `demos.scriptor-cms.dev` — USER ACTION (see §4)**.
- [ ] Email for Let's Encrypt notifications: `juri.ehret@gmail.com`
      (assumed — confirm before deploy).

---

## 4. DNS setup at IONOS — USER ACTION

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
     `scriptor-cms.dev` already resolves to — if you're not sure,
     check the existing apex A record).
   - **TTL**: keep the IONOS default (1 hour is fine).
3. (Optional, recommended) **AAAA** record with the Hetzner IPv6 if
   the box has one — saves a fallback round-trip for IPv6 clients.
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
before saving the new record — then bump it back to 3600 once
everything's stable.

---

## 5. Filesystem layout on Hetzner

```
/opt/
├── proxy/                          ← nginx-proxy + acme-companion stack
│   └── docker-compose.yml          (NEW — written in §6)
│
└── scriptor-demo/                  ← Scriptor repo, cloned via git
    ├── (entire repo contents)
    ├── docker-compose.yml          (existing — for local-dev)
    └── docker/
        └── docker-compose.prod.yml (NEW — host-deploy override, §7)
```

Both stacks share an external Docker network called `proxy`.
nginx-proxy creates the network (or we pre-create it once with
`docker network create proxy`), the scriptor stack joins it as
`external: true`.

Why two separate stacks:
- proxy is shared infra — could later host other apps under
  different `VIRTUAL_HOST`s without touching the scriptor stack
- updates are independent — `git pull && docker compose up -d` for
  Scriptor doesn't bounce the proxy or its certs
- the bundled `docker-compose.yml` stays unchanged and continues
  to work for local dev; the prod-only bits live in a sibling
  override file

---

## 6. Proxy stack — `/opt/proxy/docker-compose.yml`

```yaml
# nginx-proxy + acme-companion: shared TLS-terminating reverse proxy
# for any container that declares VIRTUAL_HOST + LETSENCRYPT_HOST.

services:
  nginx-proxy:
    image: nginxproxy/nginx-proxy:1.7
    container_name: nginx-proxy
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - certs:/etc/nginx/certs
      - vhost:/etc/nginx/vhost.d
      - html:/usr/share/nginx/html
      - /var/run/docker.sock:/tmp/docker.sock:ro
    networks:
      - proxy

  acme-companion:
    image: nginxproxy/acme-companion:2.5
    container_name: nginx-proxy-acme
    restart: unless-stopped
    depends_on:
      - nginx-proxy
    volumes_from:
      - nginx-proxy
    volumes:
      - acme:/etc/acme.sh
      - /var/run/docker.sock:/var/run/docker.sock:ro
    environment:
      DEFAULT_EMAIL: juri.ehret@gmail.com
    networks:
      - proxy

volumes:
  certs:
  vhost:
  html:
  acme:

networks:
  proxy:
    name: proxy   # explicit name so the scriptor stack joins by name
```

Image tag pinning (`1.7`, `2.5`) is intentional — protects against
upstream surprise changes. Bump deliberately when you want them.

---

## 7. Scriptor demo stack override — `docker/docker-compose.prod.yml`

NEW FILE in this repo (lands with the implementation PR, not the
plan PR). Loaded together with the bundled compose:

```bash
docker compose -f docker-compose.yml -f docker/docker-compose.prod.yml up -d
```

```yaml
# Override file for the Hetzner deploy.
# Used as a SECOND compose file on top of the bundled docker-compose.yml:
#   docker compose -f docker-compose.yml -f docker/docker-compose.prod.yml up -d
#
# Effect on the bundled stack:
#   - drops the `ports: 8080:80` host-publish on `web` (nginx-proxy
#     reaches `web` over the internal proxy network)
#   - adds VIRTUAL_HOST + LETSENCRYPT_HOST so nginx-proxy + companion
#     pick `web` up automatically
#   - joins the external `proxy` network

services:
  web:
    ports: !reset []
    environment:
      VIRTUAL_HOST: demos.scriptor-cms.dev
      VIRTUAL_PORT: "80"
      LETSENCRYPT_HOST: demos.scriptor-cms.dev
      LETSENCRYPT_EMAIL: juri.ehret@gmail.com
    networks:
      - default        # keep talking to the scriptor php-fpm container
      - proxy          # let nginx-proxy reach us

networks:
  proxy:
    external: true
    name: proxy
```

`!reset []` is a Compose-spec marker that drops the `ports` list
inherited from the base file — necessary because port mappings
otherwise merge, leaving 8080:80 published to the host. (Compose
2.24+; bundled with current `docker compose` plugin.)

---

## 8. Initial deploy procedure

Run the steps in order. ssh in once, do everything in one session.

```bash
# 0. pre-flight: from your Mac, confirm DNS is live.
dig +short demos.scriptor-cms.dev    # must show the Hetzner IP

# 1. SSH to the box.
ssh hetzner

# 2. Set up the proxy stack.
sudo install -d -m 755 -o juri -g juri /opt/proxy
cat > /opt/proxy/docker-compose.yml <<'YAML'
[paste the §6 yaml — or scp it from local first]
YAML

cd /opt/proxy
docker compose up -d
docker compose ps              # both containers should be up

# 3. Clone Scriptor.
sudo install -d -m 755 -o juri -g juri /opt/scriptor-demo
git clone https://github.com/bigin/Scriptor.git /opt/scriptor-demo
cd /opt/scriptor-demo

# 4. Bring up the demo stack with the prod override.
docker compose -f docker-compose.yml -f docker/docker-compose.prod.yml up -d --build

# 5. Watch acme-companion pick the host up + issue the cert.
docker logs -f nginx-proxy-acme   # ctrl-c when you see
                                  # "Creating/renewal of demos.scriptor-cms.dev finished"

# 6. Smoke (also runnable from your Mac).
curl -sI https://demos.scriptor-cms.dev/                    # 200
curl -sI https://demos.scriptor-cms.dev/editor/             # 200
curl -sI https://demos.scriptor-cms.dev/data/imanager.db    # 404
curl -sI http://demos.scriptor-cms.dev/                     # 301 → https
```

---

## 9. Updates

```bash
ssh hetzner
cd /opt/scriptor-demo
git pull
docker compose -f docker-compose.yml -f docker/docker-compose.prod.yml up -d --build
```

Named volumes (`scriptor-app`) survive container recreation, so the
DB and uploads persist across updates.

The proxy stack at `/opt/proxy` is independent — only update it
when bumping the pinned `nginx-proxy:1.7` / `acme-companion:2.5`
versions.

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

Uploads survive in the named volume — back up `public/uploads/`
the same way if needed:

```bash
docker run --rm -v scriptor_scriptor-app:/app -v /opt/scriptor-demo-backups:/bk \
  alpine tar czf /bk/uploads.$(date +%F).tar.gz -C /app public/uploads
```

For the demo, "if it breaks, blow it away and reseed" is also a
valid posture — `docker compose down -v` reseeds on next `up`.

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

Login: `admin` / `scriptor` (baked seed in `docker/seed-demo.php`).

---

## 12. Rollback

If the initial deploy goes sideways:

```bash
ssh hetzner
cd /opt/scriptor-demo
docker compose -f docker-compose.yml -f docker/docker-compose.prod.yml down
# DNS still points at the box, but nothing answers — site is dark
# rather than serving a broken Scriptor.

# To full-reset (also drops the volume, including the seeded DB):
docker compose -f docker-compose.yml -f docker/docker-compose.prod.yml down -v
```

The proxy stack stays up; it's tied to the host, not Scriptor. If
you also want it down: `cd /opt/proxy && docker compose down`.

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
  key in `IONOS-SCRIPTOR/` stays gitignored as a fallback.
- **nginx-proxy + acme-companion** (not Caddy). Reason: matches
  the demo image's own nginx, ≤ ~6 subdomains expected in 12
  months — well within easy operational reach.
- **Two separate compose stacks** (not one big file). Reason:
  proxy is shared infra, scriptor is one of (potentially) many
  apps later; independent lifecycles.
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
3. Open implementation branch (refactor/hetzner-demo or similar).
4. Add docker/docker-compose.prod.yml in this repo, commit, PR.
5. Merge PR.
6. SSH hetzner, follow §8 steps 1–6.
7. Verify §11 smoke matrix.
8. Mark task #54 done.
9. Resume Example-Theme (#48).
```
