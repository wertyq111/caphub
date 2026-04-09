#!/bin/sh

set -eu

if [ -d /var/www/html ]; then
    chmod -R a+rX /var/www/html || true

    for writable_path in /var/www/html/storage /var/www/html/bootstrap/cache; do
        if [ -d "$writable_path" ]; then
            chmod -R a+rwX "$writable_path" || true
        fi
    done
fi

exec "$@"
