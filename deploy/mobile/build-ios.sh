#!/bin/bash
set -e

echo "=== Fynla iOS Build ==="
echo ""

# Environment for production iOS build
export VITE_BASE_PATH=/
export VITE_API_BASE_URL=https://fynla.org
export VITE_PLATFORM=ios
export VITE_DISABLE_PWA=true

echo "1. Building web assets (env vars set above for iOS production)..."
npm run build

echo "2. Generating index.html for Capacitor..."
# Read the Vite manifest to find entry file names
APP_JS=$(python3 -c "
import json
with open('public/build/manifest.json') as f:
    m = json.load(f)
print(m['resources/js/app.js']['file'])
")
APP_CSS=$(python3 -c "
import json
with open('public/build/manifest.json') as f:
    m = json.load(f)
print(m['resources/css/app.css']['file'])
")

cat > public/build/index.html << HTMLEOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1F2A44">
    <title>Fynla</title>
    <link rel="stylesheet" href="/${APP_CSS}">
</head>
<body>
    <div id="app"></div>
    <script type="module" src="/${APP_JS}"></script>
</body>
</html>
HTMLEOF

echo "3. Removing service worker files (not needed in native app)..."
rm -f public/build/sw.js public/build/registerSW.js public/build/manifest.webmanifest

echo "4. Copying public assets for Capacitor..."
# Copy images and icons so they're available in the native app
cp -R public/images public/build/images 2>/dev/null || true
cp -R public/icons public/build/icons 2>/dev/null || true

echo "5. Syncing to iOS project..."
npx cap sync ios

echo ""
echo "=== Build complete ==="
echo "Open ios/App/App.xcworkspace in Xcode to build and archive."
echo ""
