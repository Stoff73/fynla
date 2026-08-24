---
name: ios-simulator
description: Run anything on the iOS simulator without stacking a second one on top of the one already open. Use before xcodebuild test / build / run against a simulator destination, when nothing is booted and you need one opened through Xcode, when a UI test fails with "Not hittable" on an element XCTest also calls "Keyboard Focused", when a native run hangs or dies with "Mach error -308 (ipc/mig) server died" or "Failed to install or launch the test runner", or when the laptop slows to a crawl during native work. Covers checking what is already booted, opening one via Xcode, the hardware-keyboard trap that breaks typing in UI tests, choosing that device, and the recovery ladder when CoreSimulator wedges.
---

# The iOS simulator — use the one that is already open

**The recurring failure this exists to stop:** running `xcodebuild ... -destination 'platform=iOS Simulator,name=iPhone 16'` without checking what is booted. Xcode boots a *second* device, two simulators fight for the same CoreSimulator services, the laptop grinds, and eventually every run dies at `Mach error -308 (ipc/mig) server died` — which reads like a code failure and is not one. It cost most of the morning on 2026-08-18 and a host reboot on 2026-07-23.

CSJ often has a simulator open already. **Adopt it. Never boot a rival.**

## 1. Before any simulator command — look

```bash
xcrun simctl list devices booted
```

- **One booted device** → use it, by name or UDID. Do not name a different one.
- **Nothing booted** → get one open via §1a. Do not guess a device name into `xcodebuild`.
- **More than one booted** → stop. That is the broken state; go to §3 before running anything.

Target the booted device explicitly, by UDID when you have it — a name can match several runtimes:

```bash
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination "id=<UDID-from-the-list-above>" -only-testing:FynlaTests
```

`name=iPhone 16` and `name=iPhone 16 Pro` are *different devices*. Asking for one while the other is booted is how the second simulator gets started.

## 1a. Nothing booted — open one through Xcode

`simctl boot` gives you a device with no app on it, which is not what anyone means by "the simulator is open". Drive Xcode instead, so the scheme installs and launches Fynla the way CSJ does it by hand (CSJ's instruction, 2026-08-20).

Use the **computer-use** tools (`mcp__computer-use__*`; load via ToolSearch if deferred, and call `request_access` for Xcode and Simulator first):

1. `open_application` Xcode, then screenshot to confirm the Fynla project is the front window.
2. Check the scheme in the toolbar is **`Fynla-Staging`** and the destination is the device you want. Xcode is tier **"click"** — you can click, you cannot type — so pick the destination from the menu rather than typing into it.
3. Click **Run** (the play button, top left).
4. **Wait.** First launch takes minutes: Xcode builds, boots the device, installs, then launches. Screenshot every 30s or so.
5. **Done when Fynla is actually on screen**, at the login screen — not when the simulator window appears, and not when Xcode says "Running". A booted device with a black screen or a home screen is not ready.

Then go back to §1, confirm exactly one device is booted, and take its UDID from there.

If Xcode is not installed, not licensed, or the Run button is disabled, say so and hand back — do not fall through to `simctl boot` and call it equivalent.

## 1b. Turn the hardware keyboard off before any UI test

A simulator with the hardware keyboard connected shows **no software keyboard**. XCTest still reports the field as `Keyboard Focused`, but typed text never lands, and its taps fall through to whatever is at the bottom of the screen — on Fynla that is the docked Fyn bar, which opens the chat over the page and makes every element below it `Not hittable`.

That is the whole of the `Not hittable ... Keyboard Focused` failure family (`testPR5ProjectionParityJourney`, `testPR7ParityClosureJourney`). It is not flake and it is not an app bug: the app is fine, the keystrokes simply never arrive.

Check before running UI tests:

```bash
defaults read com.apple.iphonesimulator ConnectHardwareKeyboard   # want 0
```

If it is `1` or missing:

```bash
defaults write com.apple.iphonesimulator ConnectHardwareKeyboard -bool false
```

Per-device overrides win over the global default, so on a freshly created device also add:

```bash
/usr/libexec/PlistBuddy -c "Add :DevicePreferences:<UDID>:ConnectHardwareKeyboard bool false" \
  ~/Library/Preferences/com.apple.iphonesimulator.plist
```

CI creates a brand-new simulator on every run, which is why it hits this and a developer machine does not — the preference persists locally once set. `.github/workflows/ios-native.yml` now sets it at device creation; if a UI test fails on CI with this signature and passes locally, check that step still exists before suspecting the code.

## 2. Costs worth knowing before you start

- A full `FynlaTests` run takes **6–7 minutes**; a single targeted suite still pays ~5 minutes of build. Budget for it, run it in the background, and do not assume a hang until well past that.
- Native test runs are the heaviest thing on this machine. Do not start one while a full Pest suite or a Vite build is running.

## 3. When it wedges — the ladder, in order

Symptoms: `Mach error -308 - (ipc/mig) server died`, `Failed to install or launch the test runner`, or a run that sits past ~10 minutes with no test output.

1. **Shut the devices down** — `xcrun simctl shutdown all`, wait 5s, retry once.
2. **Bounce the service** — `killall -9 com.apple.CoreSimulator.CoreSimulatorService`, wait ~8s (it restarts itself), retry once.
3. **Stop.** If a targeted run that passed earlier now hangs, the host is wedged and no amount of retrying fixes it. Say so plainly and ask CSJ to reboot. Both recorded occurrences (2026-07-23, 2026-08-18) needed a reboot; steps 1 and 2 cleared neither.

Do not loop past step 3. Three attempts is the evidence; a fourth is just time.

## 4. What a simulator run can and cannot prove

The app reads a **backend chosen by the scheme**, not by what is on this laptop:

| Scheme | Backend |
|---|---|
| `Fynla-Staging` | `https://csjones.co/fynla` |
| `Fynla-Production` | `https://fynla.org` — login cannot work yet, no native endpoints |

So a simulator run **cannot** exercise a local backend change. Unit tests (`FynlaTests`) prove decoders, reducers and view models against fixtures; anything end-to-end needs the branch deployed to csjones first. State which of the two you did — never let a green unit suite stand in for a journey.

## 5. Reporting

Say which device you used and whether you adopted or booted it. If you hit the wedge, report the ladder you climbed and stop there — `-308` is infrastructure, and calling it a test failure sends the next session hunting a bug that does not exist.
