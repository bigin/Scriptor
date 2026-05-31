# Scriptor 2.0 — demo image.
#
# php:8.3-fpm-alpine with the iManager extensions, a production-shaped
# opcache, composer install of bigins/scriptor + bigins/imanager from
# the public GitHub repo, and an entrypoint that bootstraps a fresh
# demo database on first start.

FROM php:8.3-fpm-alpine

# System libs + PHP extensions. mbstring is built-in on modern php
# images; the rest match the iManager deployment guide.
RUN set -eux; \
    apk add --no-cache \
        bash git sqlite sqlite-dev \
        oniguruma-dev libzip-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        libxml2-dev icu-dev shadow \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_sqlite mbstring gd dom zip opcache

# Opcache: demo-shaped — timestamp validation is on so a `docker compose
# restart` (or an exec'd code edit during exploration) actually picks up
# changes. For a production image, flip validate_timestamps to 0.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'realpath_cache_size=4M'; \
        echo 'realpath_cache_ttl=600'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/scriptor-demo.ini

# Composer + a writable home for it (composer.json may also pull dev
# dependencies during package metadata lookups; cache lives under
# /tmp/composer to keep image layers reproducible).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/scriptor

# Bring composer.json + composer.lock in first so the install layer
# caches independently from application source changes. The library
# is now a plain Packagist dependency (`bigins/imanager:^2.0`), so a
# straight `composer install` is enough — no repo rewrites needed.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Application code next so the previous (large) layers stay cached
# across edits to PHP source / themes / etc.
COPY . .

# Optional per-site plugins. Default install ships plugin-free. Two
# orthogonal build-args, both space-separated, both empty by default:
#
#   SCRIPTOR_PLUGIN_REPOS  WHERE to find packages not on Packagist: a
#                          list of VCS (git) URLs, each registered with
#                          `composer config repositories` before the
#                          require. Omit for Packagist-only plugins.
#   SCRIPTOR_PLUGINS       WHICH packages to install: composer specs
#                          (name:constraint).
#
# Scriptor's own composer.json names NO plugin repositories on purpose
# (it ships as a clean, generic CMS). A downstream image that installs
# non-Packagist bigins/* plugins supplies both the repo URLs and the
# package specs itself, e.g. in a compose override:
#
#   services:
#     scriptor:
#       build:
#         args:
#           SCRIPTOR_PLUGIN_REPOS: "https://github.com/bigin/scriptor-markdown-pages https://github.com/bigin/scriptor-markdown-feed"
#           SCRIPTOR_PLUGINS:      "bigins/scriptor-markdown-pages:^0.1 bigins/scriptor-markdown-feed:^0.1"
#
# Both run after `COPY . .` so the modified composer.json + composer.lock
# + vendor/ end up consistent in the final image (otherwise COPY would
# clobber the edits).
ARG SCRIPTOR_PLUGIN_REPOS=""
RUN if [ -n "$SCRIPTOR_PLUGIN_REPOS" ]; then \
        i=0; \
        for url in $SCRIPTOR_PLUGIN_REPOS; do \
            composer config "repositories.plugin$i" vcs "$url"; \
            i=$((i+1)); \
        done; \
    fi

ARG SCRIPTOR_PLUGINS=""
RUN if [ -n "$SCRIPTOR_PLUGINS" ]; then \
        composer require $SCRIPTOR_PLUGINS \
            --update-no-dev --no-interaction --prefer-dist --optimize-autoloader; \
    fi

# Entrypoint runs as root so it can chown a freshly-attached volume,
# then drops into the FPM user for the actual workload.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p data data/cache data/cache/sections data/logs data/backups data/settings public/uploads \
 && chown -R www-data:www-data /var/www/scriptor

# php-fpm listens on 9000; nginx (the sibling service) talks to us by
# service name.
EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "--nodaemonize"]
