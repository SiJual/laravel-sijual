#!/bin/sh
# Container entrypoint for the Laravel app service.
# Runs the schema migration first (the Python sidecar's Alembic baseline
# detects the tables created here and no-ops), then serves the app.
set -e

if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
    echo "[entrypoint] APP_KEY not provided — generated an ephemeral key for this container."
fi

echo "[entrypoint] running database migrations..."
php artisan migrate --force

# Seeds the system-wide expense/income categories. Idempotent: existing rows
# are detected and skipped, so this is safe on every boot.
echo "[entrypoint] seeding reference data..."
php artisan db:seed --force

echo "[entrypoint] serving on http://0.0.0.0:${APP_PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port="${APP_PORT:-8000}"
