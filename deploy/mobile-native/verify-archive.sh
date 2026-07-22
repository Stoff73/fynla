#!/bin/bash

set -euo pipefail

fail() {
    echo "error: $*" >&2
    exit 1
}

[[ $# -eq 1 ]] || fail "usage: verify-archive.sh /absolute/Fynla.xcarchive"
ARCHIVE="$1"
[[ -d "${ARCHIVE}" ]] || fail "archive does not exist: ${ARCHIVE}"

APPLICATIONS="${ARCHIVE}/Products/Applications"
[[ -d "${APPLICATIONS}" ]] || fail "archive has no Applications directory."

APP_COUNT="$(find "${APPLICATIONS}" -maxdepth 1 -type d -name '*.app' | wc -l | tr -d '[:space:]')"
[[ "${APP_COUNT}" == "1" ]] || fail "archive must contain exactly one app."
APP="$(find "${APPLICATIONS}" -maxdepth 1 -type d -name '*.app' -print)"
INFO="${APP}/Info.plist"
[[ -f "${INFO}" ]] || fail "app Info.plist is missing."

plist_raw() {
    /usr/bin/plutil -extract "$1" raw -o - "$2" 2>/dev/null
}

BUNDLE_ID="$(plist_raw CFBundleIdentifier "${INFO}")"
[[ "${BUNDLE_ID}" == "org.fynla.app" ]] || fail "unexpected bundle ID: ${BUNDLE_ID}"

MINIMUM_OS="$(plist_raw MinimumOSVersion "${INFO}")"
MINIMUM_MAJOR="${MINIMUM_OS%%.*}"
[[ "${MINIMUM_MAJOR}" =~ ^[0-9]+$ && "${MINIMUM_MAJOR}" -ge 17 ]] \
    || fail "minimum iOS must be 17.0 or later."

DEVICE_FAMILY="$(/usr/bin/plutil -extract UIDeviceFamily json -o - "${INFO}" 2>/dev/null | tr -d '[:space:]')"
[[ "${DEVICE_FAMILY}" == "[1]" ]] || fail "archive must support iPhone only."

FACE_ID="$(plist_raw NSFaceIDUsageDescription "${INFO}")"
[[ -n "${FACE_ID//[[:space:]]/}" ]] || fail "NSFaceIDUsageDescription is missing."

PRIVACY_MANIFEST="${APP}/PrivacyInfo.xcprivacy"
[[ -f "${PRIVACY_MANIFEST}" ]] || fail "PrivacyInfo.xcprivacy is missing from the app root."
/usr/bin/plutil -lint "${PRIVACY_MANIFEST}" >/dev/null \
    || fail "PrivacyInfo.xcprivacy is invalid."

EXECUTABLE_NAME="$(plist_raw CFBundleExecutable "${INFO}")"
EXECUTABLE="${APP}/${EXECUTABLE_NAME}"
[[ -f "${EXECUTABLE}" ]] || fail "app executable is missing."

/usr/bin/codesign --verify --deep --strict "${APP}" 2>/dev/null \
    || fail "app signature is invalid."

ENTITLEMENTS="$(mktemp -t fynla-entitlements.XXXXXX)"
trap 'rm -f "${ENTITLEMENTS}"' EXIT
/usr/bin/codesign -d --entitlements :- "${APP}" >"${ENTITLEMENTS}" 2>/dev/null \
    || fail "signed entitlements are unavailable."
/usr/bin/plutil -lint "${ENTITLEMENTS}" >/dev/null \
    || fail "signed entitlements are invalid."

APPLICATION_ID="$(plist_raw application-identifier "${ENTITLEMENTS}")"
case "${APPLICATION_ID}" in
    *.org.fynla.app) ;;
    *) fail "signed application identifier does not match org.fynla.app." ;;
esac

APS_ENVIRONMENT="$(plist_raw aps-environment "${ENTITLEMENTS}")"
[[ "${APS_ENVIRONMENT}" == "production" ]] \
    || fail "signed production push entitlement is missing."

ASSOCIATED_DOMAINS="$(/usr/bin/plutil -extract 'com\.apple\.developer\.associated-domains' json -o - "${ENTITLEMENTS}" 2>/dev/null | tr -d '[:space:]')"
[[ "${ASSOCIATED_DOMAINS}" == '["applinks:fynla.org"]' ]] \
    || fail "signed production associated domain is incorrect."

WEB_PAYLOAD="$(find "${APP}" -type f \( -iname '*.html' -o -iname '*.js' -o -iname '*.css' -o -iname 'cordova.js' -o -iname 'capacitor.config.*' \) -print -quit)"
[[ -z "${WEB_PAYLOAD}" ]] || fail "web bundle payload found: ${WEB_PAYLOAD}"

TEST_PAYLOAD="$(find "${APP}" -type f \( -iname '*.storekit' -o -iname '*.cer' -o -iname '*.pem' -o -iname '*.p12' -o -iname '*.key' -o -iname '*TestFixture*' \) -print -quit)"
[[ -z "${TEST_PAYLOAD}" ]] || fail "test fixture or certificate found: ${TEST_PAYLOAD}"

if [[ -d "${APP}/Frameworks" ]] && find "${APP}/Frameworks" -mindepth 1 -maxdepth 1 -print -quit | grep -q .; then
    fail "embedded third-party/native runtime framework found."
fi

if LC_ALL=C grep -R -a -F -l 'https://csjones.co' "${APP}" >/dev/null 2>&1; then
    fail "staging host found in production app bundle."
fi

# Capacitor runtime markers are intentionally narrower than the word
# "Capacitor" because the native upgrade contains a cleanup type bearing that
# historical name. It does not link or instantiate the Capacitor runtime.
for MARKER in CAPBridgeViewController CAPPlugin CDVViewController cordova WKWebView FYNLA_UI_TESTING; do
    if LC_ALL=C grep -a -F -q "${MARKER}" "${EXECUTABLE}"; then
        fail "forbidden runtime or debug marker found: ${MARKER}"
    fi
done

VERSION="$(plist_raw CFBundleShortVersionString "${INFO}")"
BUILD="$(plist_raw CFBundleVersion "${INFO}")"

echo "Bundle ID: ${BUNDLE_ID}"
echo "Version: ${VERSION} (${BUILD})"
echo "Minimum iOS: ${MINIMUM_OS}"
echo "Device family: iPhone"
echo "Associated domain: applinks:fynla.org"
echo "Push environment: production"
echo "Archive verification passed."
