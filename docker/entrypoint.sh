#!/usr/bin/env sh
set -eu

# Entrypoint for the Scriptor demo container.
#
# On first start (no SQLite DB yet) we restore a snapshot of the
# scriptor-cms.dev demo content:
#   1. docker/seed-demo.sql           — sqlite3 dump (schema + data,
#                                       captured via `imanager dump`)
#   2. docker/seed-demo-uploads.tar.gz — public/uploads/ tarball
#
# On every start we then run schema:migrate, so a dump captured on
# an older schema gets caught up if the image carries newer migrations.

APP_DIR=/var/www/scriptor
DB_PATH="${APP_DIR}/data/imanager.db"
SEED_SQL="${APP_DIR}/docker/seed-demo.sql"
SEED_UPLOADS="${APP_DIR}/docker/seed-demo-uploads.tar.gz"

cd "${APP_DIR}"

# Make sure the directories the library writes to exist and are owned by
# the FPM user. Docker volumes can come up with root ownership on first
# attach — fix that here rather than failing later.
mkdir -p data data/cache data/cache/sections data/logs data/backups data/settings public/uploads
if [ "$(id -u)" = "0" ]; then
    # `|| :` swallows EROFS on bind-mounted read-only config files
    # (e.g. an operator overlaying a custom.scriptor-config.php inside
    # data/settings/). The writable paths still get chowned; the
    # read-only entries we couldn't touch were owner-correct to begin
    # with, since the operator mounted them.
    chown -R www-data:www-data data public/uploads 2>/dev/null || :
fi

if [ ! -f "${DB_PATH}" ]; then
    if [ ! -f "${SEED_SQL}" ]; then
        echo "[entrypoint] FATAL: no DB and no seed at ${SEED_SQL}" >&2
        exit 1
    fi
    echo "[entrypoint] no database — restoring seed from ${SEED_SQL}."
    su -s /bin/sh -c "sqlite3 ${DB_PATH} < ${SEED_SQL}" www-data
    if [ -f "${SEED_UPLOADS}" ]; then
        echo "[entrypoint] extracting ${SEED_UPLOADS} into public/."
        su -s /bin/sh -c "tar xzf ${SEED_UPLOADS} -C public/" www-data
    fi
    echo "[entrypoint] seed restore complete."
fi

# Always: apply any pending schema migrations (idempotent — no-op when
# the dump's schema matches the image's migrations).
su -s /bin/sh -c "vendor/bin/imanager schema:migrate --db=${DB_PATH}" www-data

# Hand control to whatever CMD was passed (php-fpm by default).
exec "$@"
