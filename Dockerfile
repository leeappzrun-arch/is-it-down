# syntax=docker/dockerfile:1.7

FROM php:8.4-cli-bookworm AS composer_builder

ARG FLUX_USERNAME=
ARG FLUX_LICENSE_KEY=

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y git unzip libicu-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-install intl pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN cp .env.docker .env \
    && if [ -n "${FLUX_USERNAME}" ] && [ -n "${FLUX_LICENSE_KEY}" ]; then composer config http-basic.composer.fluxui.dev "${FLUX_USERNAME}" "${FLUX_LICENSE_KEY}"; fi \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && rm -rf node_modules

FROM node:22-bookworm AS asset_builder

WORKDIR /var/www/html

COPY --from=composer_builder /var/www/html /var/www/html

RUN npm ci \
    && npm run build \
    && rm -rf node_modules

FROM php:8.4-apache-bookworm AS runtime

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_DATA_PATH=/var/www/html/database/data \
    LARAVEL_SCREENSHOT_DRIVER=browsershot \
    LARAVEL_SCREENSHOT_NODE_BINARY=/usr/bin/node \
    LARAVEL_SCREENSHOT_NODE_MODULES_PATH=/opt/browsershot/node_modules \
    PUPPETEER_CACHE_DIR=/opt/browsershot/.cache/puppeteer \
    LARAVEL_SCREENSHOT_NO_SANDBOX=true

RUN apt-get update \
    && apt-get install -y \
        ca-certificates \
        fonts-liberation \
        libasound2 \
        libatk-bridge2.0-0 \
        libatk1.0-0 \
        libcups2 \
        libdbus-1-3 \
        libdrm2 \
        libgbm1 \
        libgtk-3-0 \
        libicu-dev \
        libnspr4 \
        libnss3 \
        libsqlite3-dev \
        libu2f-udev \
        libvulkan1 \
        libx11-6 \
        libx11-xcb1 \
        libxcb1 \
        libxcomposite1 \
        libxdamage1 \
        libxext6 \
        libxfixes3 \
        libxkbcommon0 \
        libxrandr2 \
        libzip-dev \
        nodejs \
        npm \
        xdg-utils \
    && docker-php-ext-install intl pdo_sqlite zip opcache \
    && a2enmod rewrite headers expires \
    && mkdir -p /opt/browsershot \
    && npm install --prefix /opt/browsershot puppeteer \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=asset_builder /var/www/html /var/www/html
COPY .env.docker /var/www/html/.env

RUN chmod +x /var/www/html/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /opt/browsershot

VOLUME ["/var/www/html/database/data", "/var/www/html/storage/app/public"]

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
