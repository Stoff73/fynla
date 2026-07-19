#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DERIVED_DATA="${FYNLA_DERIVED_DATA_PATH:-/tmp/FynlaNativeDerivedData}"

fail() {
    echo "error: $*" >&2
    exit 1
}

[[ "${DERIVED_DATA}" = /* ]] || fail "FYNLA_DERIVED_DATA_PATH must be absolute."
case "${DERIVED_DATA}" in
    "${REPO_ROOT}"|"${REPO_ROOT}"/*)
        fail "Derived data must be written outside the repository."
        ;;
esac

xcodebuild build \
    -project "${REPO_ROOT}/ios-native/Fynla.xcodeproj" \
    -scheme Fynla-Production \
    -configuration Release \
    -destination 'generic/platform=iOS' \
    -derivedDataPath "${DERIVED_DATA}" \
    CODE_SIGNING_ALLOWED=NO
