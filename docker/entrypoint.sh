#!/bin/bash
set -euo pipefail

wait_for_db() {
    local host="${DB_HOST:-db}"
    local port="${DB_PORT:-5432}"
    local tries=0
    until php -r "exit(@fsockopen('${host}', ${port}) ? 0 : 1);" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "entrypoint: gave up waiting for ${host}:${port} after ${tries} attempts" >&2
            exit 1
        fi
        echo "entrypoint: waiting for database at ${host}:${port} (${tries})..."
        sleep 2
    done
}

# Storage/cache dirs live on named volumes; ownership can be reset by the
# volume mount, so fix it up on every boot rather than only at image build.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

wait_for_db
exec "$@"
