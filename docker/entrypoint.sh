#!/usr/bin/env sh
set -eu

# Entrypoint for the Scriptor demo container.
#
# On first start (no SQLite DB yet) we:
#   1. Apply schema migrations against a fresh DB file.
#   2. Run docker/seed-demo.php to create the canonical Pages/Users
#      categories, an admin user (admin/scriptor) and one example page.
#
# On every subsequent start the entrypoint is a no-op — the DB already
# exists and the seed script is idempotent anyway.

APP_DIR=/var/www/scriptor
DB_PATH="${APP_DIR}/data/imanager.db"

cd "${APP_DIR}"

# Make sure the directories the library writes to exist and are owned by
# the FPM user. Docker volumes can come up with root ownership on first
# attach — fix that here rather than failing later.
mkdir -p data data/cache data/cache/sections data/logs data/backups data/settings public/uploads
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data data public/uploads
fi

if [ ! -f "${DB_PATH}" ]; then
    echo "[entrypoint] no database at ${DB_PATH} — bootstrapping demo data."
    # Schema migrations are auto-applied on the first PDO resolve from
    # DefaultBootstrap, so the seed script gets a fully-migrated DB for
    # free. We run schema:migrate explicitly too so a future entrypoint
    # change that doesn't open a container connection still applies
    # pending migrations.
    su -s /bin/sh -c "vendor/bin/imanager schema:migrate --db=${DB_PATH}" www-data
    su -s /bin/sh -c "php docker/seed-demo.php" www-data
    echo "[entrypoint] seed complete."
else
    echo "[entrypoint] database present — applying any pending schema migrations."
    su -s /bin/sh -c "vendor/bin/imanager schema:migrate --db=${DB_PATH}" www-data
fi

# Hand control to whatever CMD was passed (php-fpm by default).
exec "$@"
