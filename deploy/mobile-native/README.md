# Native iOS release scripts

These scripts build only the SwiftUI project in `ios-native/`. They do not run Vite, Capacitor, CocoaPods or the permanent `/m` web application.

## Compile the production target

```bash
./deploy/mobile-native/build.sh
```

This performs an unsigned Release compile with the `Fynla-Production` scheme for a generic iOS device. Derived data defaults to `/tmp/FynlaNativeDerivedData`; set `FYNLA_DERIVED_DATA_PATH` to another absolute path outside the repository when needed.

## Create a signed archive

```bash
./deploy/mobile-native/archive.sh \
  --version 1.0.0 \
  --build 2 \
  --output /tmp/Fynla-1.0.0-2.xcarchive
```

Version/build overrides are passed only as Xcode's `MARKETING_VERSION` and `CURRENT_PROJECT_VERSION` settings. Omitting either uses the reviewed project value. The script never accepts credentials, refuses output inside the repository and uses Xcode signing configuration.

Creating an archive does not upload or submit it. App Store distribution remains a separate, explicitly approved Xcode/App Store Connect action.

## Verify the archive

```bash
./deploy/mobile-native/verify-archive.sh /tmp/Fynla-1.0.0-2.xcarchive
```

Verification fails closed unless the archive is signed as `org.fynla.app`, targets iOS 17 or later and iPhone only, contains the privacy manifest and Face ID purpose, and carries production push/associated-domain entitlements. It also rejects staging URLs, test material, embedded frameworks, web bundles and Capacitor/Cordova/WKWebView runtime markers.

Use [the native v1 release checklist](../../docs/app-store/native-v1-release-checklist.md) for evidence ownership, physical-device/TestFlight gates and rollback.
