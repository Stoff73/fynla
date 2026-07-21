#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NATIVE="$ROOT/ios-native"
PROJECT="$NATIVE/Fynla.xcodeproj"
SOURCE_ICONS="$ROOT/ios/App/App/Assets.xcassets/AppIcon.appiconset"
NATIVE_ICONS="$NATIVE/Fynla/Assets.xcassets/AppIcon.appiconset"

required_files=(
  "$PROJECT/project.pbxproj"
  "$PROJECT/xcshareddata/xcschemes/Fynla-Staging.xcscheme"
  "$PROJECT/xcshareddata/xcschemes/Fynla-Production.xcscheme"
  "$NATIVE/Configurations/Base.xcconfig"
  "$NATIVE/Configurations/Staging.xcconfig"
  "$NATIVE/Configurations/Production.xcconfig"
  "$NATIVE/Fynla/App/FynlaApp.swift"
  "$NATIVE/Configurations/Info.plist"
  "$NATIVE/FynlaTests/FynlaTests.swift"
  "$NATIVE/FynlaUITests/FynlaUITests.swift"
)

for file in "${required_files[@]}"; do
  test -f "$file" || { echo "missing required file: $file" >&2; exit 1; }
done

grep -Fq '<key>FYNLA_API_BASE_URL</key>' "$NATIVE/Configurations/Info.plist"
grep -Fq '<string>$(FYNLA_API_BASE_URL)</string>' "$NATIVE/Configurations/Info.plist"
grep -Fq '<key>FYNLA_WEB_BASE_URL</key>' "$NATIVE/Configurations/Info.plist"
grep -Fq '<string>$(FYNLA_WEB_BASE_URL)</string>' "$NATIVE/Configurations/Info.plist"
grep -Fq '<key>FYNLA_ENVIRONMENT</key>' "$NATIVE/Configurations/Info.plist"
grep -Fq '<string>$(FYNLA_ENVIRONMENT)</string>' "$NATIVE/Configurations/Info.plist"

grep -Fq 'IPHONEOS_DEPLOYMENT_TARGET = 17.0' "$NATIVE/Configurations/Base.xcconfig"
grep -Fq 'SWIFT_VERSION = 6.0' "$NATIVE/Configurations/Base.xcconfig"
grep -Fq 'SWIFT_STRICT_CONCURRENCY = complete' "$NATIVE/Configurations/Base.xcconfig"
grep -Fq 'TARGETED_DEVICE_FAMILY = 1' "$NATIVE/Configurations/Base.xcconfig"
grep -Fq 'SUPPORTED_PLATFORMS = iphoneos iphonesimulator' "$NATIVE/Configurations/Base.xcconfig"
grep -Fq 'SUPPORTS_MACCATALYST = NO' "$NATIVE/Configurations/Base.xcconfig"
grep -Fq 'INFOPLIST_KEY_UISupportedInterfaceOrientations = UIInterfaceOrientationPortrait' "$NATIVE/Configurations/Base.xcconfig"
grep -Fq 'INFOPLIST_KEY_UIUserInterfaceStyle = Light' "$NATIVE/Configurations/Base.xcconfig"

grep -Fq 'PRODUCT_BUNDLE_IDENTIFIER = org.fynla.app.dev' "$NATIVE/Configurations/Staging.xcconfig"
grep -Fq 'FYNLA_API_BASE_URL = https:/$()/csjones.co/fynla' "$NATIVE/Configurations/Staging.xcconfig"
grep -Fq 'FYNLA_ENVIRONMENT = staging' "$NATIVE/Configurations/Staging.xcconfig"
grep -Fq 'PRODUCT_BUNDLE_IDENTIFIER = org.fynla.app' "$NATIVE/Configurations/Production.xcconfig"
grep -Fq 'FYNLA_API_BASE_URL = https:/$()/fynla.org' "$NATIVE/Configurations/Production.xcconfig"
grep -Fq 'FYNLA_ENVIRONMENT = production' "$NATIVE/Configurations/Production.xcconfig"

if grep -R -E 'DEVELOPMENT_TEAM[[:space:]]*=' "$NATIVE" --include='*.pbxproj' --include='*.xcconfig'; then
  echo 'personal development team must not be committed' >&2
  exit 1
fi

if grep -R -E 'Capacitor|CocoaPods|WKWebView' "$NATIVE" --include='*.swift' --include='*.pbxproj' --include='*.xcconfig'; then
  echo 'native target contains a forbidden legacy dependency' >&2
  exit 1
fi

while IFS= read -r source_icon; do
  relative="${source_icon#"$SOURCE_ICONS/"}"
  cmp "$source_icon" "$NATIVE_ICONS/$relative"
done < <(find "$SOURCE_ICONS" -maxdepth 1 -type f -name '*.png' | sort)

project_listing="$(xcodebuild -project "$PROJECT" -list -json)"
printf '%s' "$project_listing" | grep -Fq 'Fynla-Staging'
printf '%s' "$project_listing" | grep -Fq 'Fynla-Production'
printf '%s' "$project_listing" | grep -Fq 'FynlaTests'
printf '%s' "$project_listing" | grep -Fq 'FynlaUITests'

echo 'native project structure verified'
