#!/usr/bin/env bash
#
# build-mobile.sh — build the isolated mobile SPA bundle into public/m-build.
#
# Part of the "HTML mockup -> localhost SPA" workflow (see
# docs/mobile/mockup-to-app-workflow.md). Run this after changing anything
# under resources/mobile/ (Dashboard.vue, dashboard.css, api.js, etc.).
#
# IMPORTANT — config path resolution:
#   `vite build --config vite.mobile.config.js` resolves the config RELATIVE to
#   the process working directory. In some environments (e.g. a git worktree
#   whose CWD differs from the repo you are editing) that resolves to the wrong
#   copy and silently compiles stale source. To make the build deterministic we
#   cd into THIS script's repo root and pass the config as an ABSOLUTE path so
#   `__dirname` inside vite.mobile.config.js always points at this checkout.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

CONFIG="$REPO_ROOT/vite.mobile.config.js"
MANIFEST="$REPO_ROOT/public/m-build/manifest.json"

echo "==> Building mobile SPA"
echo "    repo:   $REPO_ROOT"
echo "    config: $CONFIG"

npx vite build --config "$CONFIG"

if [ ! -f "$MANIFEST" ]; then
  echo "ERROR: build produced no manifest at $MANIFEST" >&2
  exit 1
fi

# Sanity-check the manifest references a hashed entry chunk.
if ! grep -q 'assets/main-' "$MANIFEST"; then
  echo "ERROR: manifest does not reference a main bundle" >&2
  exit 1
fi

echo "==> Built. Manifest:"
cat "$MANIFEST"

# Clear Laravel caches so mobile-app.blade re-reads the fresh manifest.
php artisan view:clear   >/dev/null 2>&1 || true
php artisan cache:clear  >/dev/null 2>&1 || true
php artisan config:clear >/dev/null 2>&1 || true

echo "==> Done. Smoke-test at http://localhost:8000/m/app/dashboard"
