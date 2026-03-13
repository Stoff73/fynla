---
tags:
  - ios
  - deploy
  - testflight
  - march-2026
  - reference
---

# Deploy to TestFlight — Step-by-Step Guide

**App:** Fynla (`org.fynla.app`)
**Team ID:** `99S3M8JLLF`
**API:** `https://fynla.org`
**Current Version:** 1.0.0

> This is the repeatable deployment process. For first-time setup (Apple Developer account, App Store Connect app creation, capabilities, provisioning), see [[iOSDeployGuide]].

---

## Prerequisites Checklist

Before starting, confirm:

- [ ] Xcode 15+ installed and signed in to Apple Developer account
- [ ] Physical iPhone connected (or deploying to TestFlight for remote testers)
- [ ] All code changes committed and pushed
- [ ] Any PHP backend changes uploaded to production and cache cleared

---

## Phase 1: Build Web Assets

```bash
cd /Users/CSJ/Desktop/fynla

# 1. Seed the database (mandatory)
php artisan db:seed

# 2. Build web assets and sync to iOS project
./deploy/mobile/build-ios.sh
```

This script:
1. Sets `VITE_API_BASE_URL=https://fynla.org` and `VITE_PLATFORM=ios`
2. Runs Vite production build
3. Generates `public/build/index.html` from Vite manifest
4. Removes service worker files (not needed in native app)
5. Copies images/icons into build directory
6. Runs `npx cap sync ios` (copies web assets + pod install)

**Expected output:**
```
✔ Copying web assets from build to ios/App/App/public
✔ Updating iOS plugins
✔ Updating iOS native dependencies with pod install
[info] Found 14 Capacitor plugins for ios
✔ Sync finished
=== Build complete ===
```

**If build fails:**

| Error | Fix |
|-------|-----|
| `npm ERR!` | Run `npm install` first |
| `pod install` fails | Run `cd ios/App && pod install --repo-update && cd ../..` |
| Vite build errors | Check for TypeScript/Vue compilation errors in terminal |

---

## Phase 2: Open Xcode and Configure

```bash
open ios/App/App.xcworkspace
```

**CRITICAL:** Always open `.xcworkspace`, NOT `.xcodeproj`.

### 2a. Increment Build Number

1. Select **App** target in the left navigator
2. Go to **General** tab
3. Under **Identity**:
   - **Version** (`MARKETING_VERSION`): Only change for user-visible releases (e.g. 1.0.0 → 1.1.0)
   - **Build** (`CURRENT_PROJECT_VERSION`): Increment for EVERY TestFlight upload (e.g. 1 → 2 → 3)

> App Store Connect rejects duplicate build numbers for the same version. Always increment.

### 2b. Verify Signing

1. Go to **Signing & Capabilities** tab
2. Confirm:
   - [x] **Automatically manage signing** is checked
   - **Team:** Your Apple Developer team (`99S3M8JLLF`)
   - **Bundle Identifier:** `org.fynla.app`
   - **Provisioning Profile:** Shows "Xcode Managed Profile"
   - No signing errors (red warning icons)

### 2c. Verify Capabilities

Confirm these are present on the **Signing & Capabilities** tab:

| Capability | Configuration |
|-----------|---------------|
| Push Notifications | Enabled (the "Console" button links to Apple's developer portal — it may show a different app, that's fine, ignore it) |
| Associated Domains | `applinks:fynla.org` |
| Keychain Sharing | `org.fynla.app` |
| Background Modes | Remote notifications |

> **Note:** Push notifications are not actively wired up yet (no Firebase/APNs server config). The capability is a placeholder for future implementation. Just confirm it's listed — you don't need to configure anything in Apple's Push Notification Console.

---

## Phase 3: Test on Device (Recommended Before Archive)

1. Connect iPhone via USB
2. Select your physical device from the Xcode toolbar dropdown
3. Press **Cmd+R** to build and run
4. Quick sanity check:
   - [ ] App launches (no blank screen)
   - [ ] Login works
   - [ ] Dashboard loads with data
   - [ ] Chat opens (Fyn tab)
   - [ ] Voice input activates (mic button)
   - [ ] Face ID works (if enabled)
   - [ ] Settings/More menu looks correct

**If app shows blank screen:** See [[iosBlankScreenFix]] — likely a `vite.config.js` issue with `rollupOptions.external` or missing `transformAssetUrls: false`.

---

## Phase 4: Archive

### 4a. Select Build Destination

1. In the Xcode toolbar, change the device to: **Any iOS Device (arm64)**
   - You CANNOT archive with a Simulator selected
   - You CANNOT archive with a specific physical device selected (it works but better to use "Any iOS Device")

### 4b. Clean Build (Recommended)

1. **Product → Clean Build Folder** (Shift+Cmd+K)
2. Wait for clean to complete

### 4c. Create Archive

1. **Product → Archive**
2. Wait for the build (2-5 minutes depending on machine)
3. When done, the **Organizer** window opens automatically showing your new archive

**If archive fails:**

| Error | Fix |
|-------|-----|
| Signing error | Check Signing & Capabilities, ensure team is selected |
| Missing provisioning profile | Enable "Automatically manage signing" |
| Build error in Pods | `cd ios/App && pod install --repo-update` then retry |
| `No such module 'Capacitor'` | `npx cap sync ios` then retry |
| Duplicate symbol | Clean build folder (Shift+Cmd+K) then retry |

---

## Phase 5: Validate Archive

1. In the **Organizer** window, select your new archive
2. Click **Validate App**
3. Options (keep all checked):
   - [x] Upload your app's symbols to Apple for crash reports
   - [x] Manage version and build number
4. Select signing method: **Automatically manage signing**
5. Click **Validate**
6. Wait for validation (1-2 minutes)

**If validation fails:**

| Error | Fix |
|-------|-----|
| Missing push notification entitlement | Add Push Notifications capability in Signing & Capabilities |
| Missing privacy descriptions | Verify `NSMicrophoneUsageDescription`, `NSSpeechRecognitionUsageDescription`, `NSFaceIDUsageDescription` in Info.plist |
| Invalid bundle ID | Must match `org.fynla.app` |
| Icon missing | Check `ios/App/App/Assets.xcassets/AppIcon.appiconset` has the 1024x1024 icon |
| Invalid provisioning profile | Re-enable automatic signing, or regenerate profile at developer.apple.com |

---

## Phase 6: Upload to App Store Connect

1. In the **Organizer**, with the validated archive selected
2. Click **Distribute App**
3. Select **App Store Connect** → Click **Next**
4. Select **Upload** → Click **Next**
5. Options (keep all checked):
   - [x] Upload your app's symbols
   - [x] Manage version and build number
   - [x] Strip Swift symbols (reduces app size)
6. Signing: **Automatically manage signing** → Click **Next**
7. Review the summary → Click **Upload**
8. Wait for upload to complete (2-10 minutes depending on connection)
9. You'll see **"Upload Successful"**

The build now appears in App Store Connect. Processing takes **5-30 minutes** before it's available for TestFlight.

---

## Phase 7: TestFlight Distribution

### 7a. Check Build Processing

1. Go to [App Store Connect](https://appstoreconnect.apple.com)
2. Select **Fynla** → **TestFlight** tab
3. Wait for the build to show status **"Ready to Submit"** or **"Ready to Test"**
   - If status is **"Processing"** — wait (5-30 minutes)
   - If status is **"Missing Compliance"** — click the build and answer the export compliance question:
     - "Does this app use encryption?" → **Yes** (HTTPS/TLS)
     - "Is it exempt under Category 5 Part 2?" → **Yes** (standard HTTPS only)
     - This only needs answering once per version

### 7b. Internal Testing (Immediate — No Review)

Internal testers (up to 100 members of your App Store Connect team) can install immediately:

1. Under **Internal Testing**, find or create a group (e.g. "Fynla Team")
2. Click **+** next to Testers to add Apple ID emails
3. Ensure the latest build is selected for the group
4. Testers receive an email with TestFlight installation link
5. Testers open the link → installs TestFlight app (if not already) → installs Fynla

### 7c. External Testing (Requires Beta App Review)

For testers outside your Apple Developer team (up to 10,000):

1. Under **External Testing**, create a group (e.g. "Beta Testers")
2. Add testers by email
3. Fill in **Beta App Information** (first time only):
   - **What to Test:** "Test login, dashboard, Fyn chat (text and voice), goals, learn hub, settings. Report issues via TestFlight feedback."
   - **Contact Email:** Your email
   - **Privacy Policy URL:** `https://fynla.org/privacy`
4. Select the build
5. Click **Submit for Review**
6. Beta App Review takes **24-48 hours** first time, faster for subsequent builds

### 7d. Tester Experience

Once invited, testers:
1. Download **TestFlight** from the App Store (free)
2. Open the invitation email/link
3. Tap **Accept** in TestFlight
4. Tap **Install** to install Fynla
5. App appears on their home screen with an orange dot (TestFlight indicator)
6. Updates are automatic — when you upload a new build, testers get notified

---

## Phase 8: Post-Upload Checklist

- [ ] Build appears in App Store Connect TestFlight tab
- [ ] Export compliance answered (if prompted)
- [ ] Internal testers can install
- [ ] Test the TestFlight build yourself on a CLEAN device (delete dev build first)
- [ ] Verify login → dashboard → chat → goals → settings all work
- [ ] Verify Face ID setup works on fresh install
- [ ] Verify voice input works

---

## Quick Reference: The Fast Path

For subsequent deploys when nothing structural has changed, the entire process is:

```bash
# 1. Build
cd /Users/CSJ/Desktop/fynla
php artisan db:seed
./deploy/mobile/build-ios.sh

# 2. Open Xcode
open ios/App/App.xcworkspace

# 3. Increment Build number (General tab)
# 4. Product → Clean Build Folder (Shift+Cmd+K)
# 5. Select "Any iOS Device (arm64)"
# 6. Product → Archive
# 7. Organizer → Validate App → Distribute App → Upload
# 8. Wait 5-30 min for processing in App Store Connect
# 9. TestFlight testers get the update automatically
```

Total time: ~15-20 minutes (build: 2 min, archive: 3 min, upload: 5 min, processing: 5-30 min).

---

## Version History

| Build | Date | Changes |
|-------|------|---------|
| 1 | 2026-03-13 | Initial build — dashboard, Fyn chat, voice input, Face ID, goals, learn hub |

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| "This build is invalid" in App Store Connect | Usually a signing issue — re-archive with automatic signing |
| Build stuck in "Processing" for 1h+ | Rare Apple-side issue — wait or re-upload |
| Testers don't receive email | Check spam folder; verify email matches Apple ID |
| "Unable to Install" on tester device | Device iOS version may be below minimum (16.0); or provisioning profile doesn't include their device (internal testing only) |
| App crashes on launch (TestFlight) | Check Xcode Organizer → Crashes; most likely a missing env var or API connectivity issue |
| Blank screen after install | `vite.config.js` issue — see [[iosBlankScreenFix]]; delete app, clean build, rebuild |
| Face ID not working | Keychain Sharing capability must be enabled; test on physical device only |
| Voice input not working | Microphone + Speech Recognition permissions in Info.plist; test on physical device only |
| Chat returns error | Check production server — SSH in and `grep "AiChatService" storage/logs/laravel.log` |

### Nuclear Option: Full Clean Rebuild

If something is inexplicably broken:

```bash
# 1. Clean everything
cd /Users/CSJ/Desktop/fynla
rm -rf ios/App/App/public        # Remove old web assets
rm -rf ios/App/Pods               # Remove CocoaPods
rm -rf ios/App/Podfile.lock       # Force fresh pod resolution

# 2. Rebuild from scratch
./deploy/mobile/build-ios.sh      # Rebuilds web assets + npx cap sync (includes pod install)

# 3. In Xcode
# Product → Clean Build Folder (Shift+Cmd+K)
# Close Xcode completely
# Re-open .xcworkspace
# Build
```

### Checking What's Inside the Build

To verify the built web assets are correct before archiving:

```bash
# Check the index.html Capacitor will load
cat ios/App/App/public/index.html

# Verify no image imports in JS (would cause MIME type error)
grep -r 'import("/images' ios/App/App/public/assets/ 2>/dev/null

# Check build size
du -sh ios/App/App/public/
```
