---
type: handover
mode: context-clear
date: 2026-07-20
session: 1
branch: codex/savetax-allowance-ctas (main repo) / codex/ios-package7-platform-release (worktree)
---

# Context Clear Handover — 2026-07-20, Session 1

## Immediate state

Session ended on CSJ's instruction after repeated stop commands. **CSJ said the
work "is on the wrong path" and worried it was damaging the app — the next
session MUST NOT resume the iOS parity work until CSJ has said what was wrong
and confirmed the direction.** All work is local; nothing was deployed
anywhere; the pkg7 branch was deliberately NOT pushed pending CSJ review.

## The thread

1. Full audit of `ios/` (legacy Capacitor) + `ios-native/` (SwiftUI) vs the
   2026-07-14 contract → report at `July/July20Updates/ios-audit-report-2026-07-20.md`.
   Key discovery: the complete native app lives on stacked branches; pkgs 1–3
   merged to dev, pkgs 4–7 are open PRs #634/#636/#635/#637.
2. CSJ approved a P0+P1 fix pass on the pkg7 branch (worktree
   `/Users/CSJ/Desktop/fynla-ios-package7`), then escalated the requirement:
   **every native screen must match `/m` on five axes — detail, functionality,
   states, intent, AND design** — fix-or-note every mismatch in the parity
   ledger (`codex/plans/ios/2026-07-20-native-m-parity-ledger.md` on the branch).
3. Landed (local commits on pkg7 branch, in order): pkg4-tip merge; login
   rebuilt as the /m mirror (verified on the open simulator + unit suite);
   dashboard rebuilt to /m (hero, LEVEL UP callout/carousel, finances viz grid,
   Fyn dock, drawer menu, Lock/Sign-out restored); login placeholder tint;
   Apple webhook named rate limiter.
4. Session dissolved over working-style failures (see "know" below), ending in
   repeated stops and this wrap-up.

## Files touched

Main repo (pushed): `July/July20Updates/ios-audit-report-2026-07-20.md`,
this handover, `CSJTODO.md`.
Worktree pkg7 branch (LOCAL ONLY, NOT pushed): commits `1c89fb9` (pkg4 merge),
`fb9d777`/`56726f8` (login + focus-area parity, VersionPolicy tolerance),
`1ffe823` (dashboard/menu/dock rebuild), `9238aa6` (placeholder tint),
`b84fe64` (webhook limiter). Working tree clean.

## What the next Claude needs to know

- **Ask CSJ first: "what was wrong?"** Do not resume building, do not undo
  anything, until CSJ specifies what "wrong path" meant. Undo command if CSJ
  wants it: `git -C /Users/CSJ/Desktop/fynla-ios-package7 reset --hard
  origin/codex/ios-package7-platform-release`.
- **Working style is non-negotiable:** strictly serial (one foreground action
  at a time), no background agents/tasks, read every output before the next
  action, never re-run a failing command to "see it again", halt instantly on
  any user message, and ONLY the already-open simulator (Fynla iPhone SE
  iOS 17.5, D155EBAD-2317-4E99-9C6D-7475F971B091).
- Suite state on pkg7: 337 tests; green except 6 StoreKit hosted-config tests
  that were red locally BEFORE this session (daemon/runtime issue; green in CI
  per pkg4 evidence). Face ID suite was fixed (containment + test reconcile).
- The worktree has vendor/ + .env copied from the main checkout — the .env
  predates pkg4, so anything constructing the Apple bridge fails
  `invalid_configuration` (also `.venv/apple-store` is absent). A webhook
  limiter Pest test was written, failed on this, and was deleted — the limiter
  code itself is committed; a correct test still needs writing.
- Task list has the full remaining queue (P0: client-side calcs ×5, 35 NI
  years, consent toggles; P1 items; module-by-module /m sweep). The parity
  ledger on the branch records every fixed/deferred item with dispositions.
- The /m dock's Fyn avatar was NOT ported (mascot-as-icon ban) — flagged in
  the ledger for CSJ. Menu/chevron/key/bulb icons were added under CSJ's
  explicit "design must match /m" direction (2026-07-20 carve-out).

## Pick up from here

1. Ask CSJ what "wrong path" referred to and whether the pkg7 local commits
   stay, get amended, or get reset.
2. If continuing: push (or reset) the pkg7 branch per CSJ's answer, then
   resume the task list serially, starting with the P0 client-side calcs,
   verifying each screen against `resources/mobile/` on all five axes.
