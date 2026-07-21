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
3. **(Optional, removes every interactive gate)** App Store Connect API key —
   Users and Access → Integrations → App Store Connect API → Team Keys →
   generate with **App Manager** role. Download the `.p8` once, note the Key
   ID and Issuer ID, and place the file at
   `~/.appstoreconnect/private_keys/AuthKey_<KEYID>.p8`. With the key in
   place, every step below runs headlessly (`-authenticationKeyPath/…ID/…IssuerID`).

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
  -allowProvisioningUpdates
# With an API key, append:
#   -authenticationKeyPath ~/.appstoreconnect/private_keys/AuthKey_<KEYID>.p8 \
#   -authenticationKeyID <KEYID> -authenticationKeyIssuerID <ISSUERID>
```

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
