#!/bin/bash
# =============================================================================
# Fynla Build Script - fynla.org (ROOT deployment)
# =============================================================================
# Usage: ./deploy/fynla-org/build.sh
# Output: Builds frontend assets in public/build/
# =============================================================================
# IMPORTANT: The server does not have enough memory to run npm build.
# This script builds locally. You then manually upload changed files via
# SiteGround File Manager.
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "============================================="
echo "Fynla Build - fynla.org (ROOT deployment)"
echo "============================================="
echo ""

cd "$PROJECT_ROOT"

# Set environment variables for root deployment
export NODE_ENV=production
export VITE_BASE_PATH=/build/
export VITE_ROUTER_BASE=/
export VITE_APP_NAME="Fynla"
export VITE_API_BASE_URL=https://fynla.org

echo "Environment:"
echo "  NODE_ENV: $NODE_ENV"
echo "  VITE_BASE_PATH: $VITE_BASE_PATH"
echo "  VITE_ROUTER_BASE: $VITE_ROUTER_BASE"
echo "  VITE_API_BASE_URL: $VITE_API_BASE_URL"
echo ""

# Build frontend assets
echo "Building frontend assets..."
npm run build

if [ ! -f "public/build/manifest.json" ]; then
    echo "ERROR: Build failed - manifest.json not found"
    exit 1
fi

# Get build size
BUILD_SIZE=$(du -sh "public/build" | cut -f1)

echo ""
echo "============================================="
echo "Build complete!"
echo "============================================="
echo ""
echo "Built assets: public/build/ ($BUILD_SIZE)"
echo ""
echo "============================================="
echo "Manual Upload via SiteGround File Manager:"
echo "============================================="
echo ""
echo "1. Upload public/build/ directory to:"
echo "   ~/www/fynla.org/public_html/public/build/"
echo ""
echo "2. Upload any changed PHP files (check deployment notes)"
echo ""
echo "3. SSH to server and clear caches:"
echo "   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org"
echo "   cd ~/www/fynla.org/public_html"
echo "   php artisan cache:clear && php artisan route:clear && php artisan config:clear"
echo ""
echo "DO NOT run 'npm install' or 'npm run build' on the server!"
echo ""
