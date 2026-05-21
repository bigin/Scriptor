#!/usr/bin/env sh
set -eu

# Entrypoint for the Scriptor demo container.
#
# On first start (no SQLite DB yet) we bootstrap a fresh demo:
#   1. bin/scriptor install            seeds Pages + Users + admin +
#                                       minimal Home via the same CLI
#                                       a bare-metal install uses
#   2. docker/seed-demo-content.sql    overlays the 7 extra demo pages
#                                       (Articles, Contact, Footer, ...)
#                                       on top of the install's Home
#   3. docker/seed-demo-uploads.tar.gz the uploaded images those pages
#                                       reference, extracted into public/
#
# On every start we then run schema:migrate, so an existing DB carried
# over from an older image catches up to whatever migrations this image
# carries.
#
# Admin credentials: SCRIPTOR_ADMIN_PASSWORD from docker-compose.yml is
# passed straight to `bin/scriptor install`. The demo's default value
# is gT5nLazzyBob and matches docs/demo.md.

APP_DIR=/var/www/scriptor
DB_PATH="${APP_DIR}/data/imanager.db"
SEED_CONTENT="${APP_DIR}/docker/seed-demo-content.sql"
SEED_UPLOADS="${APP_DIR}/docker/seed-demo-uploads.tar.gz"

cd "${APP_DIR}"

# Make sure the directories the library writes to exist and are owned by
# the FPM user. Docker volumes can come up with root ownership on first
# attach, fix that here rather than failing later.
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
    if [ -z "${SCRIPTOR_ADMIN_PASSWORD:-}" ]; then
        echo "[entrypoint] FATAL: SCRIPTOR_ADMIN_PASSWORD env not set" >&2
        echo "[entrypoint] docker-compose.yml ships a default; check that the value is propagating to the container" >&2
        exit 1
    fi

    echo "[entrypoint] no database, running bin/scriptor install."
    su -s /bin/sh www-data -c "\
        SCRIPTOR_ADMIN_PASSWORD='${SCRIPTOR_ADMIN_PASSWORD}' \
        php bin/scriptor install --yes \
    "

    if [ -f "${SEED_CONTENT}" ]; then
        echo "[entrypoint] overlaying ${SEED_CONTENT}."
        su -s /bin/sh -c "sqlite3 ${DB_PATH} < ${SEED_CONTENT}" www-data
    fi

    if [ -f "${SEED_UPLOADS}" ]; then
        echo "[entrypoint] extracting ${SEED_UPLOADS} into public/."
        su -s /bin/sh -c "tar xzf ${SEED_UPLOADS} -C public/" www-data
    fi

    echo "[entrypoint] bootstrap complete."
fi

# Always: apply any pending schema migrations (idempotent, no-op when
# the DB's schema matches the image's migrations).
su -s /bin/sh -c "vendor/bin/imanager schema:migrate --db=${DB_PATH}" www-data

# Hand control to whatever CMD was passed (php-fpm by default).
exec "$@"
