#!/bin/bash
# =============================================================================
# Fynla Build Script - fynla.org (ROOT deployment)
# =============================================================================
# Usage: ./deploy/fynla-org/build.sh
# Output: Creates public/build/ with assets for root deployment
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "============================================="
echo "Building Fynla for fynla.org (ROOT deployment)"
echo "============================================="

cd "$PROJECT_ROOT"

# Set environment variables for root deployment
export NODE_ENV=production
export VITE_BASE_PATH=/build/
export VITE_APP_NAME="Fynla"
export VITE_API_BASE_URL=https://fynla.org

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
echo "Next steps:"
echo "1. Review DEPLOYMENT_FYNLA_ORG.md for full deployment guide"
echo "2. Create deployment package (see guide Step 5)"
echo "3. Upload to SiteGround and configure"
echo ""
