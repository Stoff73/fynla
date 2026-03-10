#!/bin/bash
set -e

echo "=== Fynla iOS Build ==="
echo ""

# Environment for production iOS build
export VITE_BASE_PATH=/
export VITE_API_BASE_URL=https://fynla.org
export VITE_PLATFORM=ios

echo "1. Building web assets..."
npm run build

echo "2. Syncing to iOS project..."
npx cap sync ios

echo ""
echo "=== Build complete ==="
echo "Open ios/App/App.xcworkspace in Xcode to build and archive."
echo ""
