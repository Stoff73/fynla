# Fynla iOS App — Complete Deploy Guide

**Date:** 10 March 2026
**Branch:** `feature/mobile-app-phase0`
**App Version:** 1.0.0 (Build 1)

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Apple Developer Account Setup](#2-apple-developer-account-setup)
3. [Deploy Backend to Production](#3-deploy-backend-to-production)
4. [Build Web Assets for iOS](#4-build-web-assets-for-ios)
5. [Configure Xcode Project](#5-configure-xcode-project)
6. [Add Capabilities in Xcode](#6-add-capabilities-in-xcode)
7. [Configure Signing](#7-configure-signing)
8. [Test in Simulator](#8-test-in-simulator)
9. [Test on Physical Device](#9-test-on-physical-device)
10. [Archive and Upload to App Store Connect](#10-archive-and-upload-to-app-store-connect)
11. [Configure App Store Connect](#11-configure-app-store-connect)
12. [TestFlight Distribution](#12-testflight-distribution)
13. [App Store Submission](#13-app-store-submission)
14. [Post-Launch Checklist](#14-post-launch-checklist)

---

## 1. Prerequisites

Before starting, ensure you have:

- [ ] **macOS** with the latest Xcode installed (15.x+)
- [ ] **Apple Developer Account** (https://developer.apple.com) — requires annual $99/year membership
- [ ] **Node.js 18.15+** installed locally
- [ ] **CocoaPods** installed (`sudo gem install cocoapods`)
- [ ] **Fynla repo** checked out on `feature/mobile-app-phase0` branch
- [ ] **npm dependencies** installed (`npm install` from project root)

### Local Environment Check

```bash
# Verify everything is in place
node --version          # Should be 18.15+
xcodebuild -version     # Should be 15.x+
pod --version            # Should be 1.6+
git branch               # Should show feature/mobile-app-phase0
```

---

## 2. Apple Developer Account Setup

### 2a. Register App ID

1. Go to https://developer.apple.com/account/resources/identifiers/list
2. Click **+** to register a new identifier
3. Select **App IDs** → **App**
4. Fill in:
   - **Description:** Fynla
   - **Bundle ID:** `org.fynla.app` (Explicit)
5. Under **Capabilities**, enable:
   - [x] **Push Notifications**
   - [x] **Associated Domains** (for deep links)
   - [x] **Keychain Sharing** (for biometric credential storage)
6. Click **Continue** → **Register**

### 2b. Create Push Notification Key (APNs)

1. Go to https://developer.apple.com/account/resources/authkeys/list
2. Click **+** to create a new key
3. **Key Name:** Fynla Push Key
4. Enable **Apple Push Notifications service (APNs)**
5. Click **Continue** → **Register**
6. **Download the .p8 key file** — you only get one chance
7. Note the **Key ID** (displayed on the page)
8. Note your **Team ID** from https://developer.apple.com/account/#/membership

Save these for Firebase/FCM configuration:
```
Key ID:    XXXXXXXXXX
Team ID:   XXXXXXXXXX
File:      AuthKey_XXXXXXXXXX.p8
```

### 2c. Create Provisioning Profiles

1. Go to https://developer.apple.com/account/resources/profiles/list
2. Create **two** profiles:

**Development:**
- Type: iOS App Development
- App ID: org.fynla.app
- Certificates: Select your development certificate
- Devices: Select all test devices
- Name: Fynla Development

**Distribution:**
- Type: App Store Connect
- App ID: org.fynla.app
- Certificates: Select your distribution certificate
- Name: Fynla Distribution

---

## 3. Deploy Backend to Production

The iOS app talks to `https://fynla.org`. Backend changes must be live before the app can work.

### 3a. Upload PHP Files via SiteGround File Manager

Upload to `~/www/fynla.org/public_html/`:

**New files (create directories if needed):**

| Local Path | Server Path |
|-----------|-------------|
| `database/migrations/2026_03_10_200004_add_mortgage_rate_alerts_to_notification_preferences.php` | Same relative path |
| `app/Console/Commands/SendMortgageRateAlerts.php` | Same relative path |
| `app/Notifications/MortgageRateAlertNotification.php` | Same relative path |

**Modified files (replace existing):**

| Local Path | What Changed |
|-----------|-------------|
| `app/Models/NotificationPreference.php` | Added `mortgage_rate_alerts` |
| `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php` | Added validation |
| `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php` | Added to response |
| `app/Console/Kernel.php` | Scheduled at 09:30 daily |

### 3b. Build and Upload Frontend

```bash
# Build for fynla.org (web)
./deploy/fynla-org/build.sh
```

Upload entire `public/build/` directory to `~/www/fynla.org/public_html/public/build/` via SiteGround File Manager.

### 3c. SSH Post-Deploy Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration
php artisan migrate

# Clear and optimise
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Reseed
php artisan db:seed
```

### 3d. Update AASA File for Deep Links

The `apple-app-site-association` file needs your real Team ID. Upload the corrected file:

```bash
# On the server, edit the AASA file
nano ~/www/fynla.org/public_html/public/.well-known/apple-app-site-association
```

Replace `TEAMID` with your actual Apple Developer Team ID:
```json
{
    "applinks": {
        "apps": [],
        "details": [{
            "appID": "YOUR_TEAM_ID.org.fynla.app",
            "paths": ["/protection", "/savings", "/investments", "/retirement", "/estate", "/goals", "/net-worth", "/tax", "/dashboard"]
        }]
    }
}
```

### 3e. Verify Backend is Live

```bash
# Test from local machine
curl -s https://fynla.org/api/v1/mobile/notifications/preferences -H "Authorization: Bearer YOUR_TOKEN" | head -20
```

---

## 4. Build Web Assets for iOS

From the project root:

```bash
# Build web assets and sync to iOS project
./deploy/mobile/build-ios.sh
```

This script:
1. Sets `VITE_BASE_PATH=/` and `VITE_API_BASE_URL=https://fynla.org`
2. Runs `npm run build` (Vite production build)
3. Generates `public/build/index.html` from the Vite manifest
4. Copies `public/images/` and `public/icons/` into the build directory
5. Runs `npx cap sync ios` (copies web assets into the iOS project + pod install)

**Expected output:**
```
✔ Copying web assets from build to ios/App/App/public
✔ Updating iOS plugins
✔ Updating iOS native dependencies with pod install
[info] Found 14 Capacitor plugins for ios
✔ Sync finished
```

---

## 5. Configure Xcode Project

### 5a. Open the Workspace

```bash
open ios/App/App.xcworkspace
```

**IMPORTANT:** Always open `.xcworkspace`, NOT `.xcodeproj`. The workspace includes CocoaPods dependencies.

### 5b. Set Version and Build Number

1. Select the **App** target in the navigator
2. Go to the **General** tab
3. Under **Identity**:
   - **Display Name:** Fynla
   - **Bundle Identifier:** `org.fynla.app` (should already be set)
   - **Version:** `1.0.0`
   - **Build:** `1`

For subsequent uploads, increment the Build number (1, 2, 3...). Version only changes for user-visible releases.

### 5c. Set Deployment Target

1. Still on **General** tab
2. Under **Minimum Deployments**:
   - **iOS:** `16.0` (recommended — covers 95%+ of active devices)

### 5d. Configure App Icons

The icons are already in the asset catalog. Verify:

1. Open `ios/App/App/Assets.xcassets`
2. Click **AppIcon**
3. Confirm the 1024x1024 icon is present (single icon for iOS 17+ asset catalog)

### 5e. Verify Info.plist Permissions

Open `ios/App/App/Info.plist` and confirm these entries exist:

| Key | Value | Purpose |
|-----|-------|---------|
| `NSMicrophoneUsageDescription` | Fynla uses your microphone for voice input when chatting with Fyn | Voice input |
| `NSSpeechRecognitionUsageDescription` | Fynla uses speech recognition to convert your voice to text when chatting with Fyn | Speech-to-text |
| `NSFaceIDUsageDescription` | Fynla uses Face ID for quick, secure login | Biometric auth |

These are already configured — just verify they appear.

---

## 6. Add Capabilities in Xcode

1. Select the **App** target
2. Go to the **Signing & Capabilities** tab
3. Click **+ Capability** and add each:

### Push Notifications
- Click **+ Capability** → search **Push Notifications** → double-click to add
- This enables APNs entitlement

### Associated Domains
- Click **+ Capability** → search **Associated Domains** → double-click to add
- Click **+** under Domains and add:
  ```
  applinks:fynla.org
  ```

### Keychain Sharing
- Click **+ Capability** → search **Keychain Sharing** → double-click to add
- Add a keychain group: `org.fynla.app`
- This is required for `@capgo/capacitor-native-biometric` credential storage

### Background Modes (optional, for push)
- Click **+ Capability** → search **Background Modes** → double-click to add
- Enable: **Remote notifications**

After adding all capabilities, your Signing & Capabilities tab should show:
- Push Notifications
- Associated Domains (`applinks:fynla.org`)
- Keychain Sharing (`org.fynla.app`)
- Background Modes (Remote notifications)

---

## 7. Configure Signing

### Automatic Signing (Recommended)

1. On the **Signing & Capabilities** tab
2. Check **Automatically manage signing**
3. Select your **Team** from the dropdown
4. Xcode will automatically create/download provisioning profiles

### Manual Signing (if needed)

1. Uncheck **Automatically manage signing**
2. Under **Debug**:
   - Provisioning Profile: Select "Fynla Development"
   - Signing Certificate: Apple Development
3. Under **Release**:
   - Provisioning Profile: Select "Fynla Distribution"
   - Signing Certificate: Apple Distribution

---

## 8. Test in Simulator

### 8a. Build and Run

1. Select a simulator device from the toolbar (e.g., **iPhone 15 Pro**)
2. Press **Cmd+R** or click the **Play** button
3. Wait for the build to complete and the app to launch

### 8b. Simulator Testing Checklist

- [ ] App launches with eggshell splash screen
- [ ] Login screen appears with Fynla logo
- [ ] Can enter email/password and submit
- [ ] Verification code screen appears (enter code from user)
- [ ] After login, dashboard loads with 5-tab navigation
- [ ] **Home tab:** Net worth card, Fyn insight, module grid
- [ ] **Fyn tab:** Chat interface, can send messages, receives SSE responses
- [ ] **Learn tab:** 8 topic cards, can tap into details, external links open
- [ ] **Goals tab:** Goals list with progress rings, can tap into detail
- [ ] **More tab:** Profile card, module grid, settings list
- [ ] Tab switching is smooth with no flicker
- [ ] Pull-to-refresh works on dashboard

**Note:** Biometrics, push notifications, and haptics do NOT work in Simulator. These require a physical device.

---

## 9. Test on Physical Device

### 9a. Connect Device

1. Connect iPhone via USB
2. On the iPhone: **Settings → Privacy & Security → Developer Mode** → Enable
3. Trust the computer when prompted
4. In Xcode, select your physical device from the device dropdown

### 9b. Build and Run on Device

1. Press **Cmd+R**
2. If prompted, trust the developer certificate on the iPhone:
   **Settings → General → VPN & Device Management → [Your Developer ID] → Trust**

### 9c. Physical Device Testing (in addition to Simulator tests)

- [ ] **Face ID / Touch ID:** After login + verification, biometric prompt appears
- [ ] Can enable biometrics, subsequent launches offer biometric login
- [ ] **Voice Input:** Mic button in chat, permission prompt appears, speech recognized
- [ ] **Push Notifications:** Notification settings toggles work
- [ ] **Haptic Feedback:** Felt on milestone celebrations and chat sends
- [ ] **Keyboard:** Adjusts properly in chat view, no content overlap
- [ ] **Safe Areas:** Content doesn't overlap notch/dynamic island or home indicator
- [ ] **Background/Foreground:** App resumes correctly, refreshes dashboard data
- [ ] **Share Button:** Native share sheet appears when sharing

---

## 10. Archive and Upload to App Store Connect

### 10a. Set Build Configuration to Release

1. In Xcode menu: **Product → Scheme → Edit Scheme** (or Cmd+<)
2. Select **Run** on the left
3. Change Build Configuration to **Release**
4. Select **Archive** on the left — confirm it's also **Release**
5. Close the scheme editor

### 10b. Select Device

1. In the toolbar device selector, choose **Any iOS Device (arm64)**
   - You cannot archive when a Simulator is selected

### 10c. Archive

1. **Product → Archive** (or Cmd+B won't work — must use Archive)
2. Wait for the build to complete (may take 2-5 minutes)
3. When done, the **Organizer** window opens showing your archive

### 10d. Validate the Archive

1. In the Organizer, select the archive
2. Click **Validate App**
3. Options:
   - [x] Upload your app's symbols (for crash reports)
   - [x] Manage version and build number
4. Select distribution certificate and provisioning profile
5. Click **Validate**
6. Fix any errors. Common issues:

| Error | Fix |
|-------|-----|
| Missing push entitlement | Add Push Notifications capability (Step 6) |
| Invalid provisioning profile | Regenerate at developer.apple.com |
| Missing icon | Verify AppIcon in asset catalog |
| Missing privacy descriptions | Check Info.plist (Step 5e) |

### 10e. Upload to App Store Connect

1. In the Organizer, with the archive selected
2. Click **Distribute App**
3. Select **App Store Connect** → **Upload**
4. Options:
   - [x] Upload your app's symbols
   - [x] Manage version and build number
   - [x] Strip Swift symbols (reduces size)
5. Select signing:
   - **Automatically manage signing** (recommended)
   - Or select your Distribution certificate and profile manually
6. Click **Upload**
7. Wait for upload to complete (progress bar shows in Organizer)
8. You'll see **"Upload Successful"** when done

The build will appear in App Store Connect within 5-30 minutes after processing.

---

## 11. Configure App Store Connect

Go to https://appstoreconnect.apple.com

### 11a. Create the App (first time only)

1. Click **My Apps** → **+** → **New App**
2. Fill in:
   - **Platform:** iOS
   - **Name:** Fynla
   - **Primary Language:** English (UK)
   - **Bundle ID:** org.fynla.app (select from dropdown)
   - **SKU:** `fynla-ios-001`
   - **User Access:** Full Access
3. Click **Create**

### 11b. App Information

Navigate to **App Information** in the sidebar:

| Field | Value |
|-------|-------|
| Name | Fynla |
| Subtitle | Your Financial Planning Companion |
| Category | Finance |
| Secondary Category | Lifestyle |
| Content Rights | Does not contain third-party content |
| Age Rating | 4+ (no objectionable content) |

### 11c. Pricing and Availability

1. Navigate to **Pricing and Availability**
2. **Price:** Free
3. **Availability:** United Kingdom (add other countries as needed)

### 11d. App Privacy

Navigate to **App Privacy**:

| Data Type | Collection | Usage |
|-----------|-----------|-------|
| Email Address | Collected | App Functionality, Account Registration |
| Name | Collected | App Functionality, Account Registration |
| Financial Info | Collected | App Functionality (core feature) |
| Device ID | Collected | App Functionality (push notifications) |
| Usage Data | Collected | Analytics (Plausible — privacy-first, no PII) |

Select:
- Data is NOT linked to the user's identity (Plausible analytics)
- Data IS linked to the user's identity (email, name, financial data)
- Data is used for App Functionality (primary purpose)
- Data is NOT used for tracking

### 11e. Prepare Version Information

Navigate to the version page (e.g., **1.0.0**):

**Screenshots** (required — generate from Simulator):
- iPhone 6.7" Display (iPhone 15 Pro Max): 1290 x 2796px
- iPhone 6.5" Display (iPhone 14 Plus): 1284 x 2778px
- iPhone 5.5" Display (iPhone 8 Plus): 1242 x 2208px (optional but recommended)

Take screenshots of:
1. Login screen
2. Dashboard (Home tab)
3. Fyn chat
4. Goals with progress rings
5. Learn Hub
6. More menu

**Description:**
```
Fynla is your personal financial planning companion, built for UK households.

Get a clear picture of your finances across seven key areas: Protection, Savings, Investments, Retirement, Estate Planning, Goals, and Tax Optimisation.

Key features:
• Dashboard with real-time net worth tracking
• Fyn — your AI financial assistant, available via text or voice
• Goal tracking with visual progress and milestone celebrations
• Learn Hub with curated guides from MoneyHelper, HMRC, and Pension Wise
• Module summaries with actionable insights
• Push notifications for mortgage rate expiry and goal milestones
• Biometric login with Face ID and Touch ID
• Mortgage rate alert warnings at 90, 60, and 30 days

Your data stays yours. Fynla uses privacy-first analytics with no personal data tracking.

Fynla requires a registered account at fynla.org.
```

**Keywords:**
```
financial planning,UK finance,net worth,retirement,pension,ISA,mortgage,savings,investments,estate planning,goals,budget
```

**Support URL:** `https://fynla.org/support`
**Marketing URL:** `https://fynla.org`

**What's New in This Version:**
```
Initial release of the Fynla iOS app — your financial planning companion, now in your pocket.
```

**Build:** Select the uploaded build from Step 10e (it appears after processing)

---

## 12. TestFlight Distribution

### 12a. Internal Testing (immediate)

1. In App Store Connect, go to your app → **TestFlight** tab
2. The uploaded build should appear (may take 5-30 minutes to process)
3. Under **Internal Testing**, click **+** to create a group
   - **Group Name:** Fynla Team
4. Add testers by Apple ID email
5. Click **Save**
6. Testers receive an email invitation to install via TestFlight app

Internal testers (up to 100) can install immediately after build processing — no review needed.

### 12b. External Testing (requires Beta App Review)

1. Under **External Testing**, click **+** to create a group
2. Add testers by email (up to 10,000)
3. Fill in **Beta App Information**:
   - **What to Test:** Test all screens — login, dashboard, chat with Fyn, goals, learn hub, notification settings. Report any issues with the feedback button in TestFlight.
   - **Contact Email:** Your email
   - **Privacy Policy URL:** `https://fynla.org/privacy`
4. Select the build
5. Click **Submit for Review**

Beta App Review typically takes 24-48 hours for the first submission, faster for subsequent builds.

### 12c. Iterating on TestFlight Builds

For each new build:

```bash
# 1. Make code changes
# 2. Rebuild
./deploy/mobile/build-ios.sh

# 3. Open Xcode
open ios/App/App.xcworkspace

# 4. Increment Build number (e.g., 1 → 2)
#    In Xcode: App target → General → Build

# 5. Archive and upload (repeat Step 10)
```

---

## 13. App Store Submission

When TestFlight testing is complete:

1. Go to App Store Connect → your app → **App Store** tab
2. Ensure all required fields are filled:
   - [ ] Screenshots for all required device sizes
   - [ ] Description, keywords, support URL
   - [ ] App Privacy information
   - [ ] Build selected
   - [ ] Age rating completed
   - [ ] Pricing set
3. Click **Submit for Review**

### Review Notes for Apple

Add in the **App Review Information** section:

**Notes:**
```
Fynla requires an existing account registered at https://fynla.org.

Demo credentials for review:
Email: demo@fps.com
Password: Password1!

After entering credentials, a verification code is required. Please contact us at [your email] and we will provide the code within minutes during UK business hours (9am-6pm GMT).

The app requires an active internet connection as all financial data is fetched from our secure API.
```

**Contact Information:**
- First Name, Last Name, Phone, Email

### Common Review Rejection Reasons and Fixes

| Reason | Fix |
|--------|-----|
| Login required without demo account | Provide demo credentials in review notes |
| Missing privacy policy | Add link to fynla.org/privacy |
| Incomplete metadata | Fill all required App Store fields |
| Crash on launch | Test thoroughly on physical device first |
| Insufficient functionality | Ensure all 5 tabs work with demo data |
| Guideline 4.2 (minimum functionality) | The app has 5 full screens + AI chat — should pass |

---

## 14. Post-Launch Checklist

### After App Store Approval

- [ ] Verify app is visible on the UK App Store
- [ ] Download and test on a fresh device (not a development device)
- [ ] Verify login flow works end-to-end
- [ ] Confirm push notifications are delivered
- [ ] Monitor crash reports in Xcode Organizer → Crashes
- [ ] Monitor analytics in App Store Connect → Analytics

### Firebase/FCM Setup (for push notifications in production)

Once the APNs key is configured (Step 2b), configure Firebase:

1. Go to https://console.firebase.google.com
2. Create project or select existing
3. Add iOS app: Bundle ID `org.fynla.app`
4. Download `GoogleService-Info.plist`
5. Add to `ios/App/App/` in Xcode (drag into the project navigator)
6. In Firebase Console → Project Settings → Cloud Messaging:
   - Upload the APNs key (.p8 file from Step 2b)
   - Enter Key ID and Team ID
7. Update production `.env` with the Firebase server key:
   ```
   FCM_SERVER_KEY=your_firebase_server_key
   FCM_PROJECT_ID=your_firebase_project_id
   ```

### Monitoring

- **Crash reports:** Xcode Organizer → Crashes (or integrate Firebase Crashlytics)
- **Analytics:** App Store Connect → App Analytics
- **API health:** Monitor server logs for `/api/v1/mobile/*` endpoints
- **Push delivery:** Firebase Console → Cloud Messaging → Reports

---

## Quick Reference

### App Identity

| Field | Value |
|-------|-------|
| Bundle ID | `org.fynla.app` |
| App Name | Fynla |
| Display Name | Fynla |
| Version | 1.0.0 |
| Min iOS | 16.0 |
| API Server | https://fynla.org |

### iOS Permissions

| Permission | Info.plist Key | User Prompt |
|-----------|---------------|-------------|
| Microphone | `NSMicrophoneUsageDescription` | Voice input for Fyn chat |
| Speech Recognition | `NSSpeechRecognitionUsageDescription` | Voice-to-text conversion |
| Face ID | `NSFaceIDUsageDescription` | Biometric login |
| Push Notifications | Requested at runtime | Via PushPermissionPrompt component |

### Xcode Capabilities

| Capability | Configuration |
|-----------|---------------|
| Push Notifications | Enabled |
| Associated Domains | `applinks:fynla.org` |
| Keychain Sharing | `org.fynla.app` |
| Background Modes | Remote notifications |

### Build Commands

```bash
# Build web assets + sync to iOS
./deploy/mobile/build-ios.sh

# Open Xcode
open ios/App/App.xcworkspace

# Run in Simulator (via Xcode: Cmd+R)
# Archive (via Xcode: Product → Archive)
```

### Capacitor Plugins (14)

| Plugin | Capability |
|--------|-----------|
| `@capacitor/app` | App lifecycle, back button |
| `@capacitor/browser` | In-app Safari for external links |
| `@capacitor/device` | Device info |
| `@capacitor/haptics` | Haptic feedback on milestones |
| `@capacitor/keyboard` | Keyboard handling in chat |
| `@capacitor/local-notifications` | Local notifications |
| `@capacitor/network` | Offline detection |
| `@capacitor/preferences` | Native key-value storage |
| `@capacitor/push-notifications` | Push notification registration |
| `@capacitor/share` | Native share sheet |
| `@capacitor/splash-screen` | Launch screen |
| `@capacitor/status-bar` | Status bar styling |
| `@capacitor-community/speech-recognition` | Voice input |
| `@capgo/capacitor-native-biometric` | Face ID / Touch ID |
