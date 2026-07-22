---
type: handover
mode: context-clear
date: 2026-07-21
session: 1
branch: codex/savetax-allowance-ctas (main repo) / codex/ios-package7-platform-release (worktree /Users/CSJ/Desktop/fynla-ios-package7)
---

# Context Clear Handover — 2026-07-21, Session 1

## Immediate state

Mid-way through the CSJ-directed native-iOS `/m`-parity sweep on the pkg7
branch. Ten of the module screens are fully transcribed from `/m` source,
verified, committed and PUSHED (through `f277af4`). Both working trees are
clean; nothing is uncommitted anywhere. CSJ approved pushing ("lets commit
then continue") — the earlier do-not-push freeze is LIFTED for this work.

## The thread

1. CSJ's ruling (2026-07-20, standing): the native iOS app is a **copy of
   `/m`** — every screen must match `/m` on detail, functionality, states,
   intent AND design; transcribe from `resources/mobile/` source and verify
   against the LIVE `/m` rendering, don't re-interpret.
2. Done under that ruling: shell chrome (gradient hamburger header, left
   drawer, full-width Fyn dock, page heroes + Edit-details pills), dashboard,
   Fyn overlay, Income, Expenditure, Net Worth, Savings, Investments,
   Protection, Goals, Estate, Retirement, Achievements header. Plus audit
   P0s/P1s (consent no-toggle fix; six "client-side calc" items evidenced as
   /m-parity KEEPs; tolerant enum decodes; export timeout; write-401 refresh;
   verify-project.sh fixed + in CI), the Apple webhook limiter Pest test, and
   the /m-styled ScreenStateView.
3. CSJ UI direction executed on BOTH surfaces: milestone banners (grey
   next-milestone nudge AND green Milestone-reached toast) must never obscure
   the level hero — both now sit/float BELOW the level card. Native verified
   by screenshot; **the `/m` change (commit `2772831` on
   `codex/savetax-allowance-ctas`, main repo) needs CSJ's build + deploy to
   show on csjones.**
4. Late correction applied everywhere: `/m`'s `.m-hero` is a DARK horizon-500
   card (white 44pt/900 metric) — shared `MobileHeroCard` now used by every
   converted screen; uppercase section labels are neutral500; Raspberry300
   token added.

## NEXT JOBS — "Left in the Sweep" (continue exactly here)

Work these serially with the established method (below), highest first:

1. **Tax Strategy** — transcribe `resources/mobile/views/TaxStrategy.vue`
   → `ios-native/Fynla/Features/TaxStrategy/TaxStrategyView.swift`.
   Largest remaining page (allowance bars `mts-*` classes match the ones
   already built in Savings/Goals).
2. **Holistic Plan** — `resources/mobile/views/HolisticPlan.vue`
   → `Features/HolisticPlan/HolisticPlanView.swift`.
3. **Sub-pages** (each gets: MobilePageHero + Back pill via
   `MobilePageActions(onBack:)`, dark `MobileHeroCard` where `/m` has m-hero,
   whole pounds `MoneyFormatter.gbpWhole`, uppercase neutral500 section
   labels, 80pt `MobileChromeMetrics.bottomClearance`, £-coin loader):
   - `modules/SavingsAccount.vue` → `Features/Savings/SavingsAccountView.swift`
     (NOTE: its interest maths is /m-parity — keep, see ledger P0-1)
   - `modules/InvestmentAccountDetail.vue` → investment account detail view
   - `modules/RetirementPensionDetail.vue` → `RetirementPensionView.swift`
     (35 NI years fallback is /m-parity — keep, ledger P0-6)
   - `modules/ProtectionPolicy.vue` → protection policy view (×12 premium
     default is /m-parity — keep, ledger P0-5)
   - `modules/NetWorthCategory.vue` → net-worth category view
   - `views/BalanceHistory.vue` → `BalanceHistoryView.swift` (windowDays==90
     branch is /m-parity — keep, ledger P1-6)
4. **Achievements tab content** — tabs/achievements/milestones/history body
   vs `views/Achievements.vue` (header + Back pill already done).
5. **Hero persistence across loading/error states** — `/m`'s MobileChrome
   keeps the page hero visible during loading/error; native state views
   currently replace it. Restructure the per-screen state switch so
   MobilePageHero always renders above the state content.
6. **Income/Expenditure UI-test fixture stubs** — their models are unstubbed
   in `-fynla-ui-test-mode unlocked`, so the capture harness shows error
   states; add compositions so content can be screenshot-verified.
7. **`/m`-only surfaces not yet ported** — onboarding "Finish your
   personalised tax plan with Fyn" nudge + KYC unlock bubble (needs native
   onboarding-state plumbing) and the GamificationCelebration fireworks
   (ledger D8). csjones-only bug-report FAB: do NOT port.
8. **User chat-bubble style check** — `/m` defines no `.md-fyn__msg--user`
   style; verify against a live `/m` capture turn before changing native's.
9. Remaining deferred: diagnostics wiring (ledger P1-9), `.module` route dev
   stub removal, whole-pound sweep on any remaining unconverted surface,
   legacy SubscriptionPlanSeeder note (backend).

## The established method (follow it per screen)

Read the `/m` Vue source fully → read the native Swift view → rewrite as a
literal transcription (reuse `MobileChromeScaffold` components) → build with
the explicit gate (`grep -q "BUILD SUCCEEDED"` BEFORE committing — a `&&`
chain once committed a broken build) → run the 5 journey UI tests
(`testUnlockedShellUsesTheOfflineUITestComposition`, level-wheel→achievements,
Fyn conversation, Fyn→bug-report, drawer→settings Face ID) → commit → push
(push is approved for this work). Simulator: **Fynla iPhone SE iOS 17.5,
id D155EBAD-2317-4E99-9C6D-7475F971B091** (already booted; use ONLY this
one). Capture screenshots via ParityScreenshotTests + `xcrun xcresulttool
export attachments`. Live `/m` reference: csjones `/m/app/login`,
john@example.com/password, MFA code via SSH tinker (`~/.ssh/fynlaDev`).

## What the next Claude needs to know

- The parity ledger is the running record:
  `codex/plans/ios/2026-07-20-native-m-parity-ledger.md` (pkg7 branch).
  Append dispositions as you go.
- 6 StoreKit hosted-config unit tests are red LOCALLY only (pre-existing
  daemon issue, green in CI) — never chase them.
- `/m` styling truths already established: m-hero = dark horizon-500 card;
  section labels #6B7280 → neutral500; module money = whole pounds with
  em-dash for null; status bars spring/violet/raspberry; VIEW/EDIT/UPGRADE
  actions = uppercase 11-12/700 raspberry; hairlines lightGray or horizon100
  per screen (match each Vue file's scoped CSS).
- Container `.accessibilityIdentifier` stomps child identifiers — screen ids
  live on ScrollViews; `app.unlocked` contain sits on the shell VStack.
- The `/m` milestone-banner fix (main repo `2772831`) awaits CSJ's csjones
  build+deploy; native side is live on pkg7.

## Files touched (all committed + pushed)

pkg7 branch this session: `a269963`…`f277af4` (18 commits: /m transcription
of shell/dashboard/drawer/Fyn, consent fix, P1 remediation, webhook test,
chrome scaffold, 8 module screens, milestone-banner fix, dark-hero
correction, ledger updates). Main repo: `2772831` (/m milestone-banner fix).

## Pick up from here

Start with **NEXT JOBS item 1 (Tax Strategy)**: read
`resources/mobile/views/TaxStrategy.vue` in the pkg7 worktree, then
`ios-native/Fynla/Features/TaxStrategy/TaxStrategyView.swift`, transcribe,
build-gate, journey-test, commit, push. Then continue down the list in order.
