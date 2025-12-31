#!/bin/bash
# =============================================================================
# Fynla Build Script - csjones.co/fynla (SUBDIRECTORY deployment)
# =============================================================================
# Usage: ./deploy/csjones-fynla/build.sh
# Output: Creates public/build/ with assets for subdirectory deployment
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "============================================="
echo "Building Fynla for csjones.co/fynla (SUBDIRECTORY)"
echo "============================================="

cd "$PROJECT_ROOT"

# Set environment variables for subdirectory deployment
export NODE_ENV=production
export VITE_BASE_PATH=/fynla/build/
export VITE_APP_NAME="Fynla"
export VITE_API_BASE_URL=https://csjones.co/fynla

echo "Environment:"
echo "  NODE_ENV: $NODE_ENV"
echo "  VITE_BASE_PATH: $VITE_BASE_PATH"
echo "  VITE_API_BASE_URL: $VITE_API_BASE_URL"
echo ""

# Build frontend assets
echo "Building frontend assets..."
npm run build

echo ""
echo "============================================="
echo "Build complete!"
echo "============================================="
echo ""
echo "Output directory: public/build/"
echo ""
echo "Deployment notes:"
echo "1. Upload public/build/ contents to server"
echo "2. Use deploy/csjones-fynla/.htaccess for the public folder"
echo "3. Use deploy/csjones-fynla/.env.production as template"
echo ""
