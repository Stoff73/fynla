#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

lane="${1:-all}"

run_frontend_lint() {
  npm run lint
  npm run lint:policy
}

run_lint() {
  composer lint:php
  run_frontend_lint
}

run_php() { composer quality; }
run_frontend() { npm run test:frontend; }
run_build() {
  ./deploy/csjones-fynla/build.sh
  test -f public/build/manifest.json
  test -f public/m-build/manifest.json
  ./deploy/fynla-org/build.sh
  test -f public/build/manifest.json
  test -f public/m-build/manifest.json
}

case "$lane" in
  lint) run_lint ;;
  php) run_php ;;
  frontend) run_frontend ;;
  build) run_build ;;
  browser:smoke) npm run test:e2e:smoke ;;
  browser:full) npm run test:e2e:full ;;
  all)
    run_frontend_lint
    run_php
    run_frontend
    run_build
    npm run test:e2e:smoke
    ;;
  *) echo "Unknown quality lane: $lane" >&2; exit 64 ;;
esac
