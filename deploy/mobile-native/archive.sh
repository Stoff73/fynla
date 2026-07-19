#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
VERSION=""
BUILD=""
OUTPUT="/tmp/Fynla.xcarchive"

usage() {
    cat <<'USAGE'
Usage: archive.sh [--version X.Y[.Z]] [--build N] [--output /absolute/Fynla.xcarchive]

When version or build is omitted, the reviewed Xcode project setting is used.
The archive must be written outside the repository and is signed by Xcode.
USAGE
}

fail() {
    echo "error: $*" >&2
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)
            [[ $# -ge 2 ]] || fail "--version requires a value."
            VERSION="$2"
            shift 2
            ;;
        --build)
            [[ $# -ge 2 ]] || fail "--build requires a value."
            BUILD="$2"
            shift 2
            ;;
        --output)
            [[ $# -ge 2 ]] || fail "--output requires a value."
            OUTPUT="$2"
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            fail "unknown argument: $1"
            ;;
    esac
done

if [[ -n "${VERSION}" && ! "${VERSION}" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
    fail "version must be numeric X.Y or X.Y.Z."
fi
if [[ -n "${BUILD}" && ! "${BUILD}" =~ ^[1-9][0-9]*$ ]]; then
    fail "build must be a positive integer."
fi
[[ "${OUTPUT}" = /* ]] || fail "archive output must be an absolute path."
[[ "${OUTPUT}" == *.xcarchive ]] || fail "archive output must end in .xcarchive."
case "${OUTPUT}" in
    "${REPO_ROOT}"|"${REPO_ROOT}"/*)
        fail "archive output must be outside the repository."
        ;;
esac
[[ ! -e "${OUTPUT}" ]] || fail "archive output already exists: ${OUTPUT}"

mkdir -p "$(dirname "${OUTPUT}")"

XCODEBUILD=(
    xcodebuild archive
    -project "${REPO_ROOT}/ios-native/Fynla.xcodeproj"
    -scheme Fynla-Production
    -configuration Release
    -destination 'generic/platform=iOS'
    -archivePath "${OUTPUT}"
)
if [[ -n "${VERSION}" ]]; then
    XCODEBUILD+=("MARKETING_VERSION=${VERSION}")
fi
if [[ -n "${BUILD}" ]]; then
    XCODEBUILD+=("CURRENT_PROJECT_VERSION=${BUILD}")
fi

"${XCODEBUILD[@]}"

echo "Archive created: ${OUTPUT}"
