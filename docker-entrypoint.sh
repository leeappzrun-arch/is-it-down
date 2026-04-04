#!/bin/sh

set -eu

APP_ROOT=/var/www/html
APP_DATA_PATH="${APP_DATA_PATH:-$APP_ROOT/database/data}"
APP_KEY_FILE="${APP_KEY_FILE:-$APP_DATA_PATH/app.key}"
APP_RUN_SCHEDULER="${APP_RUN_SCHEDULER:-true}"

cd "$APP_ROOT"

mkdir -p \
    "$APP_DATA_PATH" \
    "$APP_ROOT/bootstrap/cache" \
    "$APP_ROOT/storage/app" \
    "$APP_ROOT/storage/framework/cache/data" \
    "$APP_ROOT/storage/framework/sessions" \
    "$APP_ROOT/storage/framework/views" \
    "$APP_ROOT/storage/logs"

chown -R www-data:www-data "$APP_DATA_PATH" "$APP_ROOT/bootstrap/cache" "$APP_ROOT/storage"

if [ -z "${APP_KEY:-}" ]; then
    if [ -s "$APP_KEY_FILE" ]; then
        APP_KEY="$(tr -d '\r\n' < "$APP_KEY_FILE")"
    else
        APP_KEY="$(su -s /bin/sh www-data -c 'php artisan key:generate --show --no-interaction' | tr -d '\r\n')"
        umask 077
        printf '%s' "$APP_KEY" > "$APP_KEY_FILE"
        chown www-data:www-data "$APP_KEY_FILE"
    fi

    export APP_KEY
fi

su -s /bin/sh www-data -c 'php artisan app:prepare-container --no-interaction'

if [ "$APP_RUN_SCHEDULER" = "true" ]; then
    su -s /bin/sh www-data -c 'php artisan schedule:work --no-interaction --no-ansi' &
fi

exec "$@"
