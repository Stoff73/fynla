---
name: ios-simulator
description: Run anything on the iOS simulator without stacking a second one on top of the one already open. Use before xcodebuild test / build / run against a simulator destination, when a native run hangs or dies with "Mach error -308 (ipc/mig) server died" or "Failed to install or launch the test runner", or when the laptop slows to a crawl during native work. Covers checking what is already booted, choosing that device, and the recovery ladder when CoreSimulator wedges.
---

# The iOS simulator — use the one that is already open

**The recurring failure this exists to stop:** running `xcodebuild ... -destination 'platform=iOS Simulator,name=iPhone 16'` without checking what is booted. Xcode boots a *second* device, two simulators fight for the same CoreSimulator services, the laptop grinds, and eventually every run dies at `Mach error -308 (ipc/mig) server died` — which reads like a code failure and is not one. It cost most of the morning on 2026-08-18 and a host reboot on 2026-07-23.

CSJ often has a simulator open already. **Adopt it. Never boot a rival.**

## 1. Before any simulator command — look

```bash
xcrun simctl list devices booted
```

- **One booted device** → use it, by name or UDID. Do not name a different one.
- **Nothing booted** → ask CSJ to open the simulator they want, or boot exactly one yourself and say which.
- **More than one booted** → stop. That is the broken state; go to §3 before running anything.

Target the booted device explicitly, by UDID when you have it — a name can match several runtimes:

```bash
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination "id=<UDID-from-the-list-above>" -only-testing:FynlaTests
```

`name=iPhone 16` and `name=iPhone 16 Pro` are *different devices*. Asking for one while the other is booted is how the second simulator gets started.

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
