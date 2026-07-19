# Native v1 release checklist

**Scope:** replace only the App Store binary for `org.fynla.app`. The Laravel backend, desktop application and permanent `/m` route remain shared, live and independently deployable.

**Authority:** local compilation and development work are approved. Production deployment, App Store upload/submission and native purchase activation require a separate explicit CSJ approval for the exact build.

## Candidate identity

| Item | Required value | Evidence |
|---|---|---|
| Bundle ID | `org.fynla.app` | `verify-archive.sh` output |
| Xcode scheme/configuration | `Fynla-Production` / Release | archive log |
| Platform | iPhone only | archive `UIDeviceFamily = [1]` |
| Minimum OS | iOS 17 or later | archive `MinimumOSVersion` |
| Version/build | CSJ-approved values | record below before archive |
| Commit | Package 7 PR merge SHA | record below before archive |
| Archive SHA-256 | exact approved `.xcarchive` checksum | record below before TestFlight |

- Candidate version: `TBD`
- Candidate build: `TBD`
- Candidate commit: `TBD`
- Candidate archive SHA-256: `TBD`
- CSJ approval reference/date: `TBD`

## Reproducible local gate

- [ ] `./deploy/mobile-native/build.sh` exits 0.
- [ ] `./deploy/mobile-native/archive.sh --version <version> --build <build> --output /tmp/<name>.xcarchive` exits 0.
- [ ] `./deploy/mobile-native/verify-archive.sh /tmp/<name>.xcarchive` exits 0 and prints only the production identity.
- [ ] Generate Xcode's privacy report from that exact archive and reconcile it with `native-v1-privacy-inventory.md`.
- [ ] Full Laravel, frontend, desktop, `/m` and Swift compile/test gates are recorded against the same commit.
- [ ] Archive contains no staging host, debug/test UI, `.storekit` file, test certificate, embedded third-party framework, Capacitor/Cordova runtime, `WKWebView` or web bundle.

## Historical Capacitor rollback evidence

The old client source and archive remain untouched. Do not delete `ios/App/`, its build tooling or the retained archive during native release review.

| Evidence | Retained value |
|---|---|
| Source commit at archive time | `55c88839e74ba8ef70aac925f57f99c7a74e0378` (`fix(ios): update app icon with eggshell background and scaled-down logo`) |
| Local archive | `~/Library/Developer/Xcode/Archives/2026-03-13/App 13-03-2026, 11.47.xcarchive` |
| Historical identity | `org.fynla.app`, version `1.0.0`, build `1`, team `99S3M8JLLF` |
| App Store upload evidence | Apple app ID `6760545667`; distribution event `366c9fb5-5806-43b0-9d8c-f46ee0928a0d` recorded as uploaded successfully on 13 March 2026 |

- [ ] Confirm in App Store Connect that build 1 is the last accepted public Capacitor build before submission; local archive metadata proves upload, not review status.
- [ ] Copy the retained archive to the approved backup location and record its SHA-256 before native submission.
- [ ] Perform the physical upgrade-install test from the accepted Capacitor build to the native candidate; stale web/Keychain state must not unlock native.

## Privacy, policy and store metadata

- [ ] Complete every App Store Connect App Privacy answer from `native-v1-privacy-inventory.md`.
- [ ] CSJ/legal approves public-policy wording for Apple StoreKit/App Store, APNs, Revolut and the health-context statement.
- [ ] Privacy manifest, Xcode privacy report, App Privacy answers and public policy match line by line.
- [ ] App name, subtitle, description, keywords, category, support URL, privacy URL and marketing URL contain no placeholder text.
- [ ] Export compliance and the current 2026 age-rating questions are complete.
- [ ] Review credentials and verification-code instructions exist only in secure App Review fields.
- [ ] Truthful screenshots come from the exact candidate and match current required iPhone sizes.

## StoreKit and server operations

- [ ] Apple Developer agreements, tax and banking are active; the approved Revolut bank account is configured only as Apple's payout account.
- [ ] Existing App Store record and `org.fynla.app` identity are preserved.
- [ ] One subscription group contains only the approved monthly and annual products, with approved prices, localisation, screenshots, no trial and no Family Sharing.
- [ ] Sandbox and production Notification V2 URLs are configured and their TEST notifications pass.
- [ ] Production numeric Apple app ID and private API credentials remain environment-only and are never placed in Swift, documentation or logs.
- [ ] Support can trace transaction/original transaction IDs without card data or full signed payloads.

## Purchase/version rollback drill (development first)

- [ ] Record the current development values for native minimum version/build and `NATIVE_STOREKIT_PURCHASE_ENABLED`.
- [ ] Set the development purchase flag false and clear configuration cache.
- [ ] Confirm accepted clients still read entitlements, restore/reconcile purchases and acknowledge verified transactions while new purchase initiation is hidden/denied.
- [ ] Raise the development minimum build above the candidate and confirm exact HTTP 426 plus the blocking App Store update view before financial content.
- [ ] Restore the recorded development values and re-run native plus desktop/`/m` shared-backend regression.
- [ ] Keep the production purchase flag fail-safe until CSJ approves activation; rollback disables only new purchase initiation, never entitlement reads, Apple notifications or transaction audit history.

## Device and TestFlight matrix

- [ ] Physical iPhone 11-family: registration, verification, login/MFA, Face ID, all modules/details, Fyn read/write, settings/privacy/export/deletion, push and links.
- [ ] Current Face ID iPhone: same complete journey.
- [ ] Monthly and annual sandbox purchase, pending/cancelled outcome, relaunch recovery, restore and manage subscription pass.
- [ ] Cold/warm privacy-safe push and supported/unsupported universal links pass.
- [ ] Offline/poor network, background 60 seconds, force quit, memory pressure, revoked session and mandatory update pass.
- [ ] VoiceOver, XXL Dynamic Type, Reduce Motion, app-switcher privacy and portrait layouts pass.
- [ ] Internal TestFlight repeats authentication, sandbox billing, push, links, export and deletion on the uploaded candidate.
- [ ] CSJ approves the exact TestFlight build before external testing or App Review.

## Submission and monitoring

- [ ] Submit the approved app build and subscription products together where App Store Connect requires it.
- [ ] Keep `/m` live and verify shared desktop/mobile APIs after backend changes; do not couple `/m` deployment to App Store review.
- [ ] Monitor native session/replay failures, Apple verification and notifications, entitlement changes, launch/crash failures, Fyn errors and shared API errors.
- [ ] After approval, verify the public update on iPhone 11-family and the current iPhone.
- [ ] Trigger client rollback/purchase disable for incorrect Premium state, unverified Apple events, auth lockout, sensitive exposure, false Fyn write success, shared web/`/m` regression or deletion/management crash.
- [ ] Never roll back/delete Apple transaction or entitlement audit evidence during a client rollback.
- [ ] Retire only the old signed Capacitor rollback window after the post-release review; never retire `/m`.
