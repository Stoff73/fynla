#!/bin/bash
set -e

echo "=== Fynla iOS Build (SP3 mobile scaffold) ==="
echo ""

# Environment for production iOS build of the isolated mobile frontend.
export VITE_MOBILE_BASE_PATH=/
export VITE_API_BASE_URL=https://fynla.org
export VITE_PLATFORM=ios
export VITE_APP_VERSION="1.0-ios-$(git rev-parse --short HEAD 2>/dev/null || echo ios)"

echo "1. Building isolated mobile assets..."
npm run build:mobile

if [ ! -f "public/m-build/manifest.json" ]; then
    echo "ERROR: Build failed - public/m-build/manifest.json not found"
    exit 1
fi

echo "2. Generating index.html for Capacitor..."
APP_JS=$(python3 -c "
import json
with open('public/m-build/manifest.json') as f:
    m = json.load(f)
print(m['resources/mobile/main.js']['file'])
")
APP_CSS=$(python3 -c "
import json
with open('public/m-build/manifest.json') as f:
    m = json.load(f)
e = m['resources/mobile/main.js']
print((e.get('css') or [''])[0])
")

cat > public/m-build/index.html << HTMLEOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1F2A44">
    <title>Fynla</title>
    $( [ -n "$APP_CSS" ] && echo "<link rel=\"stylesheet\" href=\"/${APP_CSS}\">" )
</head>
<body>
    <div id="m-app"></div>
    <script type="module" src="/${APP_JS}"></script>
</body>
</html>
HTMLEOF

echo "3. Copying public assets for Capacitor..."
cp -R public/images public/m-build/images 2>/dev/null || true
cp -R public/icons public/m-build/icons 2>/dev/null || true

echo "4. Syncing to iOS project..."
npx cap sync ios

echo ""
echo "=== Build complete ==="
echo "iOS now loads the SP3 mobile scaffold. Native auth (token/biometric) is"
echo "deferred to the future redesign (documented in resources/mobile/README.md)."
echo ""
