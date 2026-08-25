---
type: handover
mode: context-clear
date: 2026-07-21
session: 2
branch: codex/savetax-allowance-ctas (main repo) / codex/ios-package7-platform-release (worktree /Users/CSJ/Desktop/fynla-ios-package7)
---

# Context Clear Handover — 2026-07-21, Session 2

## Immediate state

Everything CSJ asked for this session is DONE, verified and pushed; both
working trees are clean. The native app's first TestFlight build (Fynla Dev
1.0 build 1, staging backend) is uploaded, VALID, and CSJ is invited as an
internal tester. Nothing is in flight; nothing is uncommitted.

## The thread

1. **/m-parity sweep FINISHED** (pkg7, pushed through `b27fa48`): Tax
   Strategy, Holistic Plan, all six sub-pages, Achievements tab content,
   hero persistence on all 18 screens, Income/Expenditure fixture stubs,
   user chat-bubble corrected to /m truth, `.module` dev stub removed.
   Found+fixed en route: Settings Sign out sat under the Fyn dock.
2. **CSJ directives executed** (after CSJ corrected my stale summary — the
   dock avatar and level-up were already done; only fireworks/nudges were
   open): Rule 15 amended — **the Fyn character is ALWAYS allowed
   everywhere; never re-raise** (committed on BOTH branches). Level-up
   fireworks transcribed from /m's GamificationCelebration (shell overlay +
   in-chat surfaces; ack matches /m's instant non-fatal `store.ack()`).
   Onboarding "Finish your personalised tax plan with Fyn" nudge + KYC
   unlock bubble transcribed from /m Dashboard.vue. All screenshot-verified.
3. **New Fyn bubble (CSJ-requested)**: campaign terminal now voices "By the
   way — the Fynla experience is even better in the app…" as its OWN bubble
   between synthesis and celebration. Pest-pinned
   (CampaignReentryExitTest); onboarding suites 756/756; deployed to
   csjones and seen live.
4. **SaveTax campaign live E2E** (real new user Sasha Templeton,
   savetax-e2e-0721@example.com / SaveTax2026!e2e on csjones): /m funnel →
   register → Fyn onboarding (income £72k, ISA £8k, Barclays £15k, Aviva
   pension £40k, spend £2.4k/mo) → verify-navigate confirms → synthesis
   £1,046/yr → new bubble → /tax-strategy mirror. Then NATIVE login on the
   simulator: her real missed level-up fireworks fired on first open; all
   amounts pull through (net worth £63k, 9.6/6-month runway, £17,398/yr
   projection identical to /m). **Two real defects found+fixed in the
   loop**: module endpoints emit string decimals + `{}`-as-null that plain
   JSONDecoder rejects (fixed app-wide via `TolerantDecoding.swift`,
   test-pinned; 341 unit tests green, only the 6 known-local StoreKit
   reds), and pension rows showed pence (now gbpWhole).
5. **TestFlight shipped**: full pipeline fought through four real gates —
   Apple PLA re-acceptance (CSJ did), expired Xcode session (bypassed via
   new ASC API key "FynlaAI"), App-Manager keys can't cloud-sign (built a
   real distribution identity via the API instead), app records are
   web-create-only (CSJ clicked New App). Build uploaded, VALID, internal
   group created, CSJ invited. Guide: `ios-native/TESTFLIGHT.md`.

## What the next Claude needs to know

- **csjones now runs `codex/savetax-allowance-ctas`** (dev merged in at
  `eec2a1a`), NOT `dev` — switched so the bubble could go live while
  keeping the native-auth endpoints. Config cached, routes cleared. Any
  "deploy dev to csjones" instinct must account for this.
- **TestFlight/ASC infrastructure** is durable and documented:
  [[reference-testflight-asc-pipeline]] memory + `ios-native/TESTFLIGHT.md`.
  Key `683FKHT7SL`, issuer `8fad68f9-…2d60`, cert `G4DATT2CZB` in the
  `fynla-dist` keychain, profile "Fynla Dev App Store". Day-to-day: bump
  build number → archive → export. Production build is pointless until
  `dev → main` ships the native backend.
- **Live-E2E pattern** for the native app: [[reference-native-live-e2e-pattern]]
  (TEST_RUNNER_ env prefix, code-relay file — start the relay immediately,
  never gate on the file it creates; celebration-aware).
- Sasha's throwaway account remains on csjones for poking; my diagnostic
  Sanctum token was revoked.
- Session hiccup worth remembering: a Playwright storage-clear on csjones
  logged CSJ out of the site mid-session — warn before clearing storage in
  a shared browser.

## Tech debt noted (not fixed)

- `fynla-dist` keychain password is written in TESTFLIGHT.md — fine for a
  local dev-machine keychain but worth rotating/relocating before the
  production release flow.
- The MFA code-relay script lives only in the session scratchpad; the
  PATTERN is in the ledger/memory but a committed `scripts/` version would
  make reruns one command.
- `framed {}` hero-persistence helper is duplicated per screen (18 copies,
  transcription-style) — a shared component would halve it if CSJ ever
  wants the consolidation.
- `TolerantDecoding` covers Decimal/Int?/String?; non-optional Int/String
  from string tokens is uncovered (not yet needed by any payload).

## Pick up from here

Nothing is owed. Natural next moves when CSJ directs: (a) CSJ installs the
TestFlight build on his iPhone and reports; (b) the `/m` milestone-banner
fix (main repo `2772831`) still awaits CSJ's build + deploy to csjones;
(c) pkg4–7 PR chain review/merge remains open per the programme; (d) a
`dev → main` release would unlock a production TestFlight build.
