#!/usr/bin/env bash
set -euo pipefail

name="${E2E_DB_NAME:-}"

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

config_dir="$(mktemp -d "${TMPDIR:-/tmp}/fynla-e2e-config.XXXXXX")"
config_cache="$config_dir/config.php"
resolved_database="$(
  APP_ENV=e2e DB_DATABASE="$name" APP_CONFIG_CACHE="$config_cache" php -r '
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

MYSQL_PWD="${DB_PASSWORD:-}" mysql \
  -h "${DB_HOST:-127.0.0.1}" \
  -P "${DB_PORT:-3306}" \
  -u "${DB_USERNAME:-root}" \
  -e "CREATE DATABASE IF NOT EXISTS \`$name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

APP_ENV=e2e DB_DATABASE="$name" APP_CONFIG_CACHE="$config_cache" php artisan migrate --force
APP_ENV=e2e DB_DATABASE="$name" APP_CONFIG_CACHE="$config_cache" php artisan db:seed --force
