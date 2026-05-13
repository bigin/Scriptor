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
    } > /usr/local/etc/php/conf.d/scriptor-demo.ini

# Composer + a writable home for it (composer.json may also pull dev
# dependencies during package metadata lookups; cache lives under
# /tmp/composer to keep image layers reproducible).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/scriptor

# Bring composer.json in first so the install layer caches independently
# from application source changes.
COPY composer.json composer.lock ./

# The repo's composer.json declares a `path` repository at `../imanager`
# for local development. Inside the container that path doesn't exist, so
# we swap repository[0] for a VCS repo pointing at the public iManager
# GitHub repo. Then update (not install) so the lock can be regenerated
# against the VCS source.
RUN composer config repositories.0 '{"type": "vcs", "url": "https://github.com/bigin/imanager"}' \
 && rm -f composer.lock \
 && composer update --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Application code last so the previous layers (large) stay cached
# across edits to PHP source / themes / etc.
COPY . .

# Entrypoint runs as root so it can chown a freshly-attached volume,
# then drops into the FPM user for the actual workload.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p data data/uploads-2.0 data/cache data/cache/sections data/logs data/backups data/settings \
 && chown -R www-data:www-data /var/www/scriptor

# php-fpm listens on 9000; nginx (the sibling service) talks to us by
# service name.
EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "--nodaemonize"]
