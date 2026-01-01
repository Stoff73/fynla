#!/bin/bash
# =============================================================================
# Fynla Build Script - fynla.org (ROOT deployment)
# =============================================================================
# Usage: ./deploy/fynla-org/build.sh
# Output: Creates fynla-deploy.zip ready to upload to server
# =============================================================================
# IMPORTANT: The server does not have enough memory to run npm build.
# This script builds everything locally and creates a deployment package.
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DEPLOY_DIR="$PROJECT_ROOT/../fynla-org-deploy"
ZIP_FILE="$PROJECT_ROOT/../fynla-org-deploy.zip"

echo "============================================="
echo "Fynla Build - fynla.org (ROOT deployment)"
echo "============================================="
echo ""
echo "IMPORTANT: Building locally because server lacks memory"
echo ""

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
echo "Step 1: Building frontend assets..."
npm run build

if [ ! -f "public/build/manifest.json" ]; then
    echo "ERROR: Build failed - manifest.json not found"
    exit 1
fi

echo "Build successful!"
echo ""

# Create deployment package
echo "Step 2: Creating deployment package..."

# Clean up previous deployment
rm -rf "$DEPLOY_DIR"
rm -f "$ZIP_FILE"
mkdir -p "$DEPLOY_DIR"

# Copy all files except excluded ones
echo "Copying files..."
rsync -a --progress "$PROJECT_ROOT/" "$DEPLOY_DIR/" \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude 'storage/logs/*.log' \
  --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  --exclude 'deploy' \
  --exclude '*.md' \
  --exclude 'CLAUDE.md' \
  --exclude '.DS_Store' \
  --exclude '*.zip'

# Copy production .htaccess
echo "Copying production .htaccess..."
cp "$SCRIPT_DIR/.htaccess" "$DEPLOY_DIR/public/.htaccess"

# Create the ZIP file
echo "Creating ZIP archive..."
cd "$PROJECT_ROOT/.."
zip -rq "fynla-org-deploy.zip" "fynla-org-deploy/"

# Get file size
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)

echo ""
echo "============================================="
echo "Build complete!"
echo "============================================="
echo ""
echo "Deployment package: $ZIP_FILE"
echo "Package size: $ZIP_SIZE"
echo ""
echo "Package includes:"
echo "  - public/build/ (compiled frontend assets)"
echo "  - vendor/ (PHP dependencies)"
echo "  - Production .htaccess"
echo ""
echo "Package excludes:"
echo "  - node_modules/ (not needed on server)"
echo "  - .git/"
echo "  - tests/"
echo "  - .env files"
echo ""
echo "============================================="
echo "Next steps:"
echo "============================================="
echo "1. Upload fynla-org-deploy.zip to server"
echo "2. Extract on server"
echo "3. Create .env file with production credentials"
echo "4. Run post-upload commands (see DEPLOYMENT_FYNLA_ORG.md)"
echo ""
echo "DO NOT run 'npm install' or 'npm run build' on the server!"
echo "The server does not have enough memory for these commands."
echo ""
