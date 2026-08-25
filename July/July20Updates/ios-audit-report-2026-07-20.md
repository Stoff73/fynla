# iOS Audit Report — `ios/` (legacy Capacitor) and `ios-native/` (native SwiftUI)

**Date:** 2026-07-20
**Scope:** read-only audit — no code changed
**Audited against:** the locked 2026-07-14 native migration contract, the 10 canonical plan docs (`codex/plans/programme/`, `codex/plans/ios/`), and CLAUDE.md rules
**Method:** four parallel audit agents — plan distillation, native core/auth/StoreKit, native feature screens, backend contract + legacy Capacitor — plus direct git-topology verification.

---

## The single most important finding — where the code actually lives

What sits at `ios-native/` on the working branch (`codex/savetax-allowance-ctas`) is **only the Package 2 foundation** (~35 files, no feature screens). The complete app — auth, Face ID, StoreKit, dashboard, Fyn, all 11 financial modules, privacy/release tooling — exists on a **stacked chain of package branches**, checked out at `/Users/CSJ/Desktop/fynla-ios-package7`:

| Package | Content | PR | State |
|---|---|---|---|
| 1 — API readiness / contract freeze | Backend contracts, parity ledger, native client headers | #630 + #632 | **Merged to dev** |
| 2 — SwiftUI foundation | Xcode project, API client, SSE, design system, CI | #631 | **Merged to dev** |
| 3 — Native auth + Face ID | Session envelope, keychain, all auth screens | #633 | **Merged to dev** (2026-07-18) |
| 4 — StoreKit + entitlements | Apple billing, Python verifier bridge, subscription UI | #634 | **Open** → dev |
| 5 — Dashboard, navigation, Fyn | Dashboard, gamification, achievements, Fyn chat | #636 | **Draft** → pkg4 |
| 6 — Financial waves | Income, Expenditure, Net Worth, Savings, Investment, Retirement, Protection, Estate, Goals, Tax Strategy, Holistic Plan | #635 | **Draft** → pkg5 |
| 7 — Platform + release | Settings, privacy/GDPR, push, universal links, version policy, archive pipeline | #637 | **Draft** → pkg6 |

Still to land on dev: **59 commits — 242 Swift files (+24,282 lines) and 103 backend files (+9,954 lines)**. Nothing has gone to main/production.

Two wrinkles:

- **(a)** Package 4's final six CI-fix commits were made *after* Package 5 branched, so the package-7 tip you'd build from today is missing them (the PR chain reconciles this at merge time, but a build from the pkg7 tip is not the final code).
- **(b)** The pkg7 worktree has **uncommitted changes** to `LoginView.swift` and `FynlaUITests.swift` — covered under "Bad" below. As the worktree stands, `FynlaTests` is red.

---

## THE GOOD — implemented as promised

**Contract compliance (core promises) is genuinely strong:**

- **Server-authoritative, thin client — the central promise — holds almost everywhere.** No tier gates or entitlement branching in Swift; feature gating arrives as server 403 `upgrade_required`; Estate teaser/full mode, balance-history windows, savings account limits, ISA allowances, recommendations, projections all rendered from backend figures. Holistic Plan even has a test named `decodesBackendRankAndAffordabilityWithoutClientRecalculation`. No hardcoded tax values (with one exception, below), no secrets, no wrong-environment URLs.
- **Session envelope fully implemented server-side and asserted in tests:** 15-minute access token (`NativeSessionService.php:57`), 30-day rotating refresh, 90-day absolute lifetime anchored to bootstrap, replay → whole-family revocation with `lockForUpdate` and forked-process race tests. Client side: 60-second background lock implemented (`PrivacyLockController.swift:61`) and tested on both sides of the boundary (59 s stays unlocked, 60 s locks and wipes tokens).
- **Keychain done right:** `WhenUnlockedThisDeviceOnly` + `.biometryCurrentSet`, non-synchronisable, refresh credential only (access token memory-only), rotation saved to keychain *before* the user fetch so a transient failure can't strand the only valid token.
- **StoreKit exactly per contract:** two products only (`org.fynla.premium.monthly`/`annual`), £6.99/£59.99 (699/5999 pence in `TierConfigurationSeeder`), no trial (trial schema removed by migration), `transaction.finish()` only after server acknowledgement, restore via server reconcile, web-billed Premium shows "manage on the web". Apple + Revolut resolve to **one** provider-neutral entitlement in `PremiumEntitlementResolver`; a stale `users.tier` cannot grant Premium. Apple verification uses Apple's official Python library behind a hash-locked bridge (the incomplete PHP verifier was properly rejected and documented in `docs/security/`).
- **Fyn one-surface contract (INV-2.4.1) correctly implemented:** one endpoint (`POST /api/ai-chat/conversations/{id}/messages`), invariant "Message Fyn" placeholder, no persona pill, the decoder has no `handoff` case at all, capture confirmation is a neutral "Saving…/Saved" line. SSE parsing is byte-safe (CRLF/CR/LF, UTF-8 splits, size caps, 202-queued branch, bounded 409 retry, no-double-post on uncertain acceptance).
- **Design rules clean where it's committed code:** zero emoji, zero SF Symbols/decorative icons in Features, zero scores (the level wheel / "X of Y actions" / percentile is the approved gamification and is intact), British spelling, canonical ownership enums, acronyms spelled out — "Stocks & Shares ISA", "Defined Contribution"; the Holistic Plan even ships an acronym expander.
- **Platform hygiene:** iOS 17 / iPhone-only / portrait-only / Swift 6 strict concurrency with warnings-as-errors; staging vs production split correct (`csjones.co/fynla` + `org.fynla.app.dev` vs `fynla.org` + `org.fynla.app`); no ATS exceptions; CI runs the full suite on an **iPhone 11** simulator per the hardware baseline; `FYNLA_UI_TESTING` scaffolding cannot reach a production archive.
- **Privacy/GDPR flows are complete and careful:** 3-step account deletion with Apple-billing warning and local cleanup only after server acknowledgement; data export with bounded polling, `.completeFileProtection` temp file deleted after the share sheet; push payloads route-key-only; bug report shows exactly what metadata is attached.
- **Backend guardrails:** Rule 7 handled deliberately — native session routes are *intentionally not* excluded from `PreviewWriteInterceptor` (preview users must never mint native credentials) and that's pinned by tests; all new routes use named limiters; Fyn endpoint confirmed cookie/CSRF-free for bearer-token native use, 202-queued envelope contract-frozen.
- **Test depth is unusual for a v1:** ~60 Swift test files with exact-endpoint and exact-value assertions (not smoke), plus deep Pest suites including contract-freeze tests (`tests/Feature/Contracts/ClientCompatibilityContractTest.php`) pinning every envelope the native client consumes.
- **Legacy `LegacyCapacitorCleanup` is correct:** the storage keys it wipes (`m_scaffold_token` localStorage, `kSecClassInternetPassword` for fynla.org/csjones.co) exactly match what the Capacitor app actually stores.

---

## THE BAD — defects and contract violations

All file references are in the pkg7 worktree (`/Users/CSJ/Desktop/fynla-ios-package7`).

**Contract violations — client-side financial calculation (the one promise the code breaks):**

1. Savings interest computed in Swift: balance × rate / 100, ÷12 — `ios-native/Fynla/Features/Savings/SavingsAccountView.swift:189-195`.
2. Savings emergency runway computed client-side (totalCash ÷ monthly expenditure) when the backend already produces `emergency_fund_months` — `SavingsView.swift:279-285`.
3. Defined Contribution monthly contribution computed from percentages × salary in Swift — `RetirementModels.swift:122-132`.
4. Income gap recomputed client-side while the server's `income_gap` is decoded then **ignored** — `RetirementModels.swift:54-57`.
5. Protection annual premium ×12/×4 in Swift, with a real bug: unknown frequency defaults to ×12, so a weekly premium would be understated ~4.3× — `ProtectionModels.swift:141-148`.
6. **Hardcoded pension rule value:** 35 National Insurance qualifying years as a fallback — `RetirementPensionView.swift:166`.

**Contract/CSJ-ruling conflicts:**

7. **AI-chat consent toggle exists in native Privacy Settings** (`PrivacySettingsView.swift:169-175`) — directly contradicts the settled "consent at registration, NO UI toggle" contract. Worse, the *required* consents (terms/privacy/data-processing) also render as live switches the user can flip off (:82-115).
8. **Rule 9 violation:** "Estimated IHT" on the dashboard estate card — `Dashboard/ModuleSummaryView.swift:77` (EstateView itself spells it out correctly).
9. **Uncommitted `LoginView.swift` rework** (verification modal): breaks committed `LoginModelTests.swift:287` (deletes the `login.verification.submit` button the test asserts), adds SF Symbols icons (`envelope`, `xmark`) on an ask-CSJ-first surface, hardcodes non-token colours/spacing (`Color.white`, `.black.opacity(0.52)`, raw padding), and auto-submits at 6 digits with no manual submit affordance (VoiceOver users with a partial code have no path forward).

**Defects:**

10. **`scripts/verify-project.sh` always exits 1** — its forbidden-legacy grep now matches the app's own `LegacyCapacitorCleanup` code — and no CI workflow invokes it, so the guardrail is both broken and unenforced.
11. **Apple Notifications V2 webhook has no rate limiter** (`routes/api.php:133-134`; the Revolut webhook has one) — an unauthenticated flood forces a Python-bridge process spawn per request: a cheap denial-of-service lever. Only the global `throttle:api` limiter stands in the way.
12. **Strict enum decoding can kill whole screens:** an unknown server value for dashboard `status`/`severity`/`action.kind` or `EstateMode` fails the entire decode → "We could not load your dashboard". No unknown-case fallback (`DashboardModels.swift:3-7,130-162`; `EstateModels.swift:8-11`).
13. **Silent failures in Retirement:** `analysis`/`projections` fetched with `try?` — a failed analyse quietly renders a screen with no recommendations and no error (`RetirementModel.swift:25-26,64-65`).
14. **Data export dead-end:** after 6 poll attempts the state stays `.processing` forever — permanent spinner, no timeout message or retry (`DataExportModel.swift:52`).
15. **Mutating requests never attempt token refresh on 401** (`APIClient.swift:56-67` — GET/HEAD only): background <60 s, return after the 15-minute token lapses, tap a write action → hard generic failure. Never-replaying writes is right; never *refreshing* before surfacing "try again" is a gap.
16. **Diagnostics layer is dead code:** `RedactingDiagnosticsClient` is well-built, well-tested, wired into `AppDependencies` — and has zero `record()` call sites.
17. **Design-token gap:** the native asset catalogue has **no spring (success) colour**; success semantics (gains, "On track") use violet — which CLAUDE.md reserves for warnings — and some error text uses raspberry, which doubles as the action colour. Needs a deliberate ruling, not an accident.
18. Minor: dashboard emergency-fund months interpolates a raw `Decimal` ("3.428571 months of emergency funding", `ModuleSummaryView.swift:71`); "ISA allowance used / of £20,000" row reads broken (`SavingsView.swift:240`); balance-history Free/Premium UI keyed on magic `windowDays == 90` (`BalanceHistoryView.swift:66,80`); vestigial `.module` route resolves to a dev-facing "staged development build" stub (`NavigationDestinationFactory.swift:191-210`); `.settings` route exempt from the unlocked-session requirement (latent, unreachable today, `AppRouter.swift:22-24`); legacy `SubscriptionPlanSeeder` still seeds standard/family/pro plans alongside the Free/Premium collapse.

**Legacy `ios/` (Capacitor):**

19. **Stale-bundle risk:** `ios/App/App/public/` assets date from **2026-05-25**; `public/m-build/` was rebuilt 2026-07-15 with different hashes and never `cap sync`'d. Anyone archiving from `ios/App` today ships an 8-week-stale bundle; nothing prevents it mechanically. Otherwise the target is coherent-but-frozen (untouched in git since 2026-03-13, correct config, buildable, biometric plumbing declared but never called from JS) and Package 7 correctly ships its cleanup.

---

## NOT DONE — promised but absent or unfinished

- **Merge state:** Packages 4–7 (~34k lines) are unmerged; #636/#635/#637 are still drafts stacked on each other.
- **Every human/physical gate from Package 3 onward is open:** physical-device Face ID matrix (opt-in, cold unlock, cancel, lockout, 60-second relock), physical iPhone 11 register/verify, Apple **sandbox purchase evidence on real hardware/TestFlight** (monthly + annual, renewal/grace/refund/revoke via signed notifications), push and universal links on device, keychain-across-installs. Simulator evidence exists; the plans explicitly say simulator is not release evidence.
- **CSJ approvals pending per the plans' own gate ledger:** Package 1 final sign-off, Package 4 sandbox evidence approval, Package 5, Package 6 (full iPhone 11 physical pass), Package 7 TestFlight build approval. The parity ledger (`docs/architecture/client-parity-ledger.md`) has handoff blocks only for Packages 1–4; **no evidence rows exist for Packages 5, 6, or 7**.
- **Release track not started:** `docs/app-store/native-v1-release-checklist.md` is entirely TBD/unchecked — no App Store Connect config, no TestFlight build, no archive produced.
- **Feature parity gaps (native vs web/`/m`):** Estate full mode is thin (value + counts only — no Inheritance Tax liability figure, no gift/trust drill-down) and is the only module with **no "Update with Fyn" entry** (gifts/trusts/will cannot be edited natively at all); Investment has no recommendations section; dashboard `alerts` and `newMilestones` are decoded but never rendered; Property has no drill-down beyond Net Worth category rows (consistent with `/m`, but shallow).
- **Package 4–7 plan checkboxes** remain `[ ]` in the plan files despite the code existing — the plans' own definition-of-done is unrecorded.

---

## DELTA — promised vs delivered, in one view

| Contract promise | Delivered? |
|---|---|
| Native SwiftUI third client, no Capacitor/WKWebView | ✅ Yes |
| No financial calcs / tier gates / entitlements in Swift | ⚠️ ~95% — 5 client-side calcs + 1 hardcoded value (items 1–6) |
| iPhone-only, portrait, iOS 17, iPhone 11 baseline | ✅ Yes, CI-enforced |
| Registration, sign-in, verification, recovery, Face ID | ✅ All flows present; physical Face ID evidence outstanding |
| 15 min / 30 day / 90 day / replay / 60 s lock | ✅ Implemented and test-asserted (server + client) |
| StoreKit Free/Premium, no trial, £6.99/£59.99 | ✅ Yes; real-device sandbox evidence outstanding |
| Apple + Revolut → one provider-neutral entitlement | ✅ Yes |
| `/m` permanent and untouched | ✅ Yes — no `/m` regressions introduced |
| Package order 1→7, one PR per package | ✅ Followed; 1–3 merged, 4–7 pending |
| Release (TestFlight → App Store) | ❌ Not started — by design, gated on CSJ approvals |

**Net position:** the programme is roughly **"code complete, release incomplete"**. Packages 1–3 are merged and gate-recorded green. Packages 4–7 are built to a high standard but sit in an unmerged draft chain with unrecorded gates, open device-evidence requirements, and the ~20 findings above.

---

## REMEDIATION LIST (prioritised)

### P0 — before the PR chain merges

1. Resolve the uncommitted `LoginView` verification-modal work: fix `LoginModelTests`, strip/get-approval for the SF Symbols icons, replace hardcoded colours/spacing with tokens, restore a manual submit affordance — or revert it.
2. Remove the five client-side financial calculations — render server figures instead (backend already supplies `emergency_fund_months` and `income_gap`; add server fields for savings interest, DC contribution, annual premium if needed). Fixes the weekly-premium ×12 bug for free.
3. Remove the hardcoded 35 National Insurance qualifying years — take it from the backend.
4. "Estimated IHT" → "Estimated Inheritance Tax" (`ModuleSummaryView.swift:77`).
5. CSJ ruling then fix: delete the AI-chat consent toggle (contract says no toggle) and make required consents non-editable display rows.
6. Add a named rate limiter to `POST /api/webhooks/apple/v2`.

### P1 — before TestFlight

7. Fix `verify-project.sh` (exclude the migration files from the legacy grep) and wire it into `ios-native.yml` — currently a broken, unenforced guardrail.
8. Add unknown-case tolerance to the strict enums so one new server value can't blank the dashboard/Estate.
9. Surface Retirement analysis failures (drop the `try?` swallow); give data-export polling a timeout state with retry.
10. Decide on write-path 401s: refresh-then-prompt-retry instead of hard failure.
11. Wire the diagnostics client into API/auth/SSE paths — or delete it.
12. Add the spring success colour to the asset catalogue and stop using violet (warning) for success semantics; separate error text from the action colour.
13. Render or drop the decoded-but-unused dashboard `alerts`/`newMilestones`; replace the `windowDays == 90` magic with a server capability flag; tidy the minor copy defects (raw Decimal months, "of £20,000" row).

### P2 — programme/process

14. Land the merge train in order (#634 → #636 → #635 → #637), confirming Package 4's six post-branch CI-fix commits reconcile into the final merge.
15. Execute the outstanding device-evidence matrix: physical Face ID, physical iPhone 11, sandbox purchases + Apple notification lifecycle, push, universal links, keychain-across-installs, Capacitor-upgrade cleanup.
16. Record Package 5–7 handoff blocks in the parity ledger and tick the plan checkboxes to match reality; obtain the pending CSJ gate approvals (P1 final, P4 sandbox, P5, P6 physical pass, P7 TestFlight build).
17. Capacitor: either re-run `deploy/mobile/build-ios.sh` if it will ever be archived again, or mark it explicitly mothballed (rollback-only per the release checklist) so a stale 2026-05-25 bundle can't ship by accident. Remove or gate the legacy `SubscriptionPlanSeeder` plans.
18. Parity decisions for v1.x: Estate full-mode depth + Fyn edit entry for Estate/Holistic Plan, Investment recommendations section, Property drill-down.
