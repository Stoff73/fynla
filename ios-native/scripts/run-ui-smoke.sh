#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PROJECT="$ROOT/ios-native/Fynla.xcodeproj"
DESTINATION="${FYNLA_SIMULATOR_DESTINATION:-platform=iOS Simulator,name=iPhone 11}"

build_settings=(
  CODE_SIGNING_ALLOWED=NO
  CODE_SIGNING_REQUIRED=NO
  COMPILER_INDEX_STORE_ENABLE=NO
)

if [[ -n "${EXCLUDED_SOURCE_FILE_NAMES:-}" ]]; then
  build_settings+=("EXCLUDED_SOURCE_FILE_NAMES=$EXCLUDED_SOURCE_FILE_NAMES")
fi

xcodebuild \
  -project "$PROJECT" \
  -scheme Fynla-Staging \
  -destination "$DESTINATION" \
  -only-testing:FynlaUITests \
  test \
  "${build_settings[@]}"
