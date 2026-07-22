# TestFlight — step-by-step guide (native SwiftUI app)

Two builds exist. **Which one to upload matters:**

| Scheme | Backend | Bundle ID | Works today? |
|---|---|---|---|
| `Fynla-Staging` | csjones.co/fynla | `org.fynla.app.dev` | **Yes** — csjones has the native auth endpoints |
| `Fynla-Production` | fynla.org | `org.fynla.app` | **No login yet** — production is pre-native-packages; ship `dev → main` first |

Until the native packages reach production, the TestFlight build that actually
works is **Fynla-Staging**. It needs its own App Store Connect app record
(`org.fynla.app.dev`) so its builds never touch the live `org.fynla.app`
listing.

Team: `99S3M8JLLF` (set in `Configurations/Base.xcconfig`). Versioning:
`MARKETING_VERSION` 1.0 / `CURRENT_PROJECT_VERSION` — every upload needs a
build number that has not been used for that version on that app record.
Encryption compliance is declared in `Configurations/Info.plist`
(`ITSAppUsesNonExemptEncryption = false` — the app only uses standard HTTPS),
so builds do not stall on the "Missing Compliance" question.

## Step 0 — REQUIRED FIRST: accept Apple's updated licence agreement

The 2026-07-21 upload attempt failed with:

> "PLA Update available: You currently don't have access to this membership
> resource. To resolve this issue, agree to the latest Program License
> Agreement in your developer account."

Apple has issued an updated Program License Agreement and, until the
**Account Holder** accepts it, every developer-services call fails —
capability registration, profile creation and uploads included (the
push/associated-domains profile errors in the same log are downstream of
this). Fix: sign in at **developer.apple.com** (and check
appstoreconnect.apple.com for a banner too) → agree to the updated
agreement. Nobody but the Account Holder can do this. Then re-run the
archive command below — nothing else changes.

## One-time setup (App Store Connect — needs your Apple ID)

1. **App record** — appstoreconnect.apple.com → My Apps → “+” → New App:
   - Platform iOS, Name e.g. “Fynla Dev”, Language English (UK),
     Bundle ID `org.fynla.app.dev` (register it at
     developer.apple.com → Identifiers first if it is not in the dropdown,
     with **Push Notifications** and **Associated Domains** capabilities
     ticked), SKU e.g. `fynla-dev`.
   - (The production record for `org.fynla.app` already exists from the
     legacy app — the production scheme uploads there when its time comes.)
2. **Signing certificate** — nothing manual needed if Xcode is signed in as
   your Apple ID (Xcode → Settings → Accounts): automatic signing creates the
   Apple Distribution certificate and App Store profile on demand during
   export (`-allowProvisioningUpdates`). If a command below fails with an
   authentication error, open Xcode → Settings → Accounts, confirm the
   `99S3M8JLLF` team is listed, and click "Download Manual Profiles" once.
3. **API key (DONE 2026-07-21)** — key "FynlaAI", ID `683FKHT7SL`, issuer
   `8fad68f9-bd52-4057-98ca-7c179a862d60`, installed at
   `~/.appstoreconnect/private_keys/AuthKey_683FKHT7SL.p8`. App Manager
   role: uploads and API provisioning run headlessly; only Xcode's cloud
   signing is out of its reach (see the signing note below).

## Build → archive → upload (repeat for every build)

From `ios-native/`:

```bash
# 1. Bump the build number (must be unique per version per app record)
#    Edit CURRENT_PROJECT_VERSION in Fynla.xcodeproj, or pass it inline as below.

# 2. Archive (device SDK, release-shaped Staging configuration)
xcodebuild archive \
  -project Fynla.xcodeproj \
  -scheme Fynla-Staging \
  -destination 'generic/platform=iOS' \
  -archivePath build/Fynla-Staging.xcarchive \
  CURRENT_PROJECT_VERSION=<N> \
  -allowProvisioningUpdates

# 3. Export + upload straight to App Store Connect
cat > build/ExportOptions.plist <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>method</key>
    <string>app-store-connect</string>
    <key>destination</key>
    <string>upload</string>
    <key>teamID</key>
    <string>99S3M8JLLF</string>
</dict>
</plist>
PLIST

xcodebuild -exportArchive \
  -archivePath build/Fynla-Staging.xcarchive \
  -exportOptionsPlist build/ExportOptions.plist \
  -authenticationKeyPath ~/.appstoreconnect/private_keys/AuthKey_683FKHT7SL.p8 \
  -authenticationKeyID 683FKHT7SL \
  -authenticationKeyIssuerID 8fad68f9-bd52-4057-98ca-7c179a862d60
```

**Signing note (as proven on 2026-07-21):** the FynlaAI API key has the App
Manager role, which can archive with `-allowProvisioningUpdates` but is NOT
allowed to mint distribution certificates through Xcode's cloud signing
("Cloud signing permission error" — that path needs an Admin key). The
working setup instead uses a real distribution identity created once via the
App Store Connect API and installed locally:

- Apple Distribution certificate `G4DATT2CZB` (expires 2027-07-21), private
  key held in the local `fynla-dist` keychain (password `fynla-temp-2026`,
  added to the user keychain search list).
- App Store profile "Fynla Dev App Store" installed under
  `~/Library/MobileDevice/Provisioning Profiles/`.
- `ExportOptions.plist` therefore uses **manual** signing:

```xml
<key>signingStyle</key><string>manual</string>
<key>signingCertificate</key><string>Apple Distribution</string>
<key>provisioningProfiles</key>
<dict><key>org.fynla.app.dev</key><string>Fynla Dev App Store</string></dict>
```

These survive between uploads — day-to-day releases are just: bump build
number → archive → exportArchive. Only when the certificate expires
(2027-07) does the one-time identity setup repeat.

The production app (`org.fynla.app`) will need its own App Store profile
against the same certificate when its time comes — one extra
`POST /v1/profiles` call.

Xcode GUI equivalent: open the project, select the `Fynla-Staging` scheme +
"Any iOS Device", Product → Archive, then in the Organizer choose
Distribute App → TestFlight & App Store → Upload.

## After the upload

1. App Store Connect → the app → **TestFlight** tab. The build appears within
   ~5–15 minutes ("Processing" first). No compliance prompt should appear
   (declared in the Info.plist).
2. **Internal testing** — create an Internal Testing group, add yourself (App
   Store Connect users) — internal builds need no Beta App Review and are
   installable the moment processing finishes.
3. On the iPhone: install **TestFlight** from the App Store, accept the email
   invitation (or it simply appears under the app if your Apple ID is the
   ASC user), install, and sign in with a csjones account.
4. External testers (later) need Beta App Review — internal is enough for
   your own devices.

## Gotchas

- **"No profiles / cannot create profile"** → the bundle ID isn't registered
  or lacks Push/Associated Domains capabilities — fix under
  developer.apple.com → Identifiers, or let Xcode's automatic signing
  register it (requires the Apple ID session).
- **"No suitable application records were found"** → the App Store Connect
  app record for the bundle ID doesn't exist yet (one-time setup step 1).
- **Duplicate build number** → bump `CURRENT_PROJECT_VERSION`.
- **Login fails inside a TestFlight build of Fynla-Production** → expected
  until the native backend packages ship `dev → main`; use the Staging build.
- The associated domain in the Staging build is `applinks:csjones.co`;
  universal links on the dev build follow csjones, not fynla.org.
