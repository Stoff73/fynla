---
id: W-0416
title: iOS native carries two copies of the goal status vocabulary and cannot say Overdue, so it reads "Behind" for a goal whose date has gone
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: deferred-ios
severity: medium
surfaces: [ios]
created: 2026-08-23T03:00:00Z
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0411]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised from W-0411 so that iOS is **named rather than assumed** (Rule 19 — surfaces are
never "the app").

**What W-0411 already fixed for iOS, at the backend:** an overdue goal no longer reports
`is_on_track: true`, so `GoalsView.swift:80` — *"Goals on track — X of Y"* — is now
correct, and the false claim is gone from the native app with no Swift change and no
build.

**What it did not fix:** the native app composes its own label from the boolean, in **two
places**:

```
ios-native/Fynla/Features/Goals/GoalModels.swift:85   return isOnTrack ? "On track" : "Behind"
ios-native/Fynla/Features/Goals/GoalsView.swift:231   return goal.isOnTrack ? "On track" : "Behind"
```

That is the same Rule 20 shape the web had before W-0411 — two copies of one vocabulary,
neither of which can express **Overdue** or **Achieved late**, because a boolean has two
values and the question has more. A goal four months past its date reads **"Behind"**, and
one past its date but fully funded reads "Behind" as well.

The server already serves the answer: `GoalResource` now returns `status_label` and
`is_overdue`, and web and `/m` read them.

## Acceptance

1. `GoalSummary` decodes `status_label` and `is_overdue`.
2. **Both** composition sites are replaced by that one field — replacing one and leaving
   the other is the defect, not the fix.
3. `status_label` is optional in the decoder, so a build running against an older backend
   degrades to the existing label rather than failing to decode the goals list entirely.
4. The fixture `FynlaTests/Fixtures/Financial/Goals/goals-list.json` gains an **overdue**
   goal. Both goals in it are currently `is_on_track: true`, so nothing in the native suite
   enters this branch (`tests/CLAUDE.md` §4, Fixture variant).
5. Needs an Xcode build and a TestFlight run; it is not a backend-only change.

## Notes

Deliberately **not** folded into F-0029. That batch was scoped to web and `/m`; a Swift
change plus a device build is a different verification loop, and claiming iOS parity
without running one would be exactly the "verified" claim `feedback_never_claim_verified`
forbids.

---

## Deferred 2026-09-01 — iOS is out of scope for the board loop

CSJ ruled on 2026-08-31 that the board loop covers web and `/m` only, and every iOS
item defers rather than being worked. This item's `surfaces` is `[ios]` alone, so all
of it defers. No Swift was changed and nothing was verified on a simulator.

The backend and `/m` halves named in the item are unaffected and remain available to
whoever picks the native work up.
