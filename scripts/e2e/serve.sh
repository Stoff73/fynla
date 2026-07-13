#!/usr/bin/env bash
set -euo pipefail

target="${1:-}"

case "$target" in
  laravel)
    name="${E2E_DB_NAME:-${DB_DATABASE:-}}"

    if [[ ! "$name" =~ ^[A-Za-z0-9_]+$ ]]; then
      echo "Refusing non-E2E database name: $name" >&2
      exit 64
    fi

    case "$name" in
      *_e2e|fynla_e2e_*) ;;
      *)
        echo "Refusing non-E2E database name: $name" >&2
        exit 64
        ;;
    esac

    env \
      VITE_ROUTER_BASE=/ \
      VITE_MOBILE_BASE_PATH=/m-e2e-build/ \
      VITE_MOBILE_OUT_DIR=public/m-e2e-build \
      npm run build:mobile

    config_dir="$(mktemp -d "${TMPDIR:-/tmp}/fynla-e2e-config.XXXXXX")"
    config_cache="$config_dir/config.php"
    routes_cache="$config_dir/routes.php"
    resolved_database="$(
      APP_ENV=e2e DB_DATABASE="$name" APP_CONFIG_CACHE="$config_cache" APP_ROUTES_CACHE="$routes_cache" php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $connection = config("database.default");
        echo config("database.connections.{$connection}.database");
      '
    )"

    if [[ "$resolved_database" != "$name" ]]; then
      echo "Laravel resolved a non-E2E database: $resolved_database" >&2
      exit 64
    fi

    exec env \
      APP_ENV=e2e \
      APP_URL=http://127.0.0.1:8000 \
      DB_DATABASE="$name" \
      APP_CONFIG_CACHE="$config_cache" \
      APP_ROUTES_CACHE="$routes_cache" \
      php artisan serve --host=127.0.0.1 --port=8000
    ;;
  vite)
    exec npm run dev -- --host 127.0.0.1 --port 5173
    ;;
  *)
    echo "Unknown E2E server target: $target" >&2
    exit 64
    ;;
esac
