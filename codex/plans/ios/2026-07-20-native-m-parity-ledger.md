# Native ↔ /m Parity Ledger — 2026-07-20

Working rule (CSJ direction, 2026-07-20): every native screen must match its
`/m` counterpart on five axes — **detail** (sections/fields/copy/order),
**functionality** (actions/gating), **states** (loading/empty/error/offline/
upgrade), **intent**, and **design** (layout/palette/typography per
`fynlaDesignGuide.md`). Mismatches are fixed in place or recorded here with a
disposition. Native-only surfaces sanctioned by a package plan are marked
BY-PLAN, not invented.

Reference: `resources/mobile/` (router.js + views). Native: `ios-native/Fynla/Features/`.

Status values: FIXED (aligned in this pass) · NOTED-DEFERRED (needs CSJ call
or its own slice) · BY-PLAN (intentional native extra) · OPEN (not yet swept).

---

## Auth — Login (`/m /login` → `Features/Authentication/LoginView.swift`)

| # | Axis | Mismatch | Disposition |
|---|------|----------|-------------|
| A1 | Design/intent | Native verification was a modal card over the blurred sign-in screen — `/m` swaps steps inside one card on a gradient brand page with the Fynla logo. | **FIXED** — rebuilt as `/m` mirror: gradient (Horizon500 → #2C2466 → /m 130%-overshoot blend, new `LoginGradientMid`/`LoginGradientBottom` colorsets), logo imageset `LogoFynlaLight` ported from `public/images/logos/`, white card radius 20 (`FynlaSpacing.cardCornerRadius`) + design-guide card shadow, in-card step swap. |
| A2 | Functionality | Native auto-submitted at 6 digits with no submit button; `/m` requires explicit "Verify and continue" (disabled until 6 digits). | **FIXED** — auto-submit removed; `/m` button restored (`login.verification.submit`). |
| A3 | Detail (copy) | Titles/copy diverged ("Sign in to Fynla" vs "Sign in"; "Enter Verification Code" vs "Enter verification code"; "We sent a code to X" vs `/m`'s "We sent a 6-digit code to **X**. Enter it below to continue."; "Cancel" vs "Use a different account"; missing "New to Fynla? Create an account" footer; missing placeholder copy). | **FIXED** — all copy now matches `/m` verbatim, spam-folder footer always shown. |
| A4 | Design | Error text was plain on the sign-in step; `/m` shows a tinted banner on both steps. | **FIXED** — shared `messageBanner` (Raspberry100 bg, Raspberry700 text) on both steps. Digit boxes now Eggshell500 (#F7F6F4) per `/m`. |
| A5 | Design | Envelope glyph in the verification info banner: `/m` has it (SVG in white circle chip on Raspberry100 banner). Rule 15 would normally ban a new icon, but CSJ's 2026-07-20 direction is "design must match /m" — treated as the Rule 15 "icons by design" carve-out. | **FIXED** — restored in `/m` banner-chip form, `accessibilityHidden` per `/m`'s `aria-hidden`. |
| A6 | Functionality | Native lockout countdown ("Try again in N seconds") has no `/m` equivalent (`/m` shows the server error only). Server enforces lockout for every client. | **BY-PLAN kept** — richer surfacing of a real server state; flag to CSJ if unwanted. |
| A7 | Functionality | "Forgot your password?" link + full in-app registration/MFA/restore flows: `/m` has neither (links out to the web funnel; email-code only). | **BY-PLAN** — Package 3 / contract requires native registration, recovery, MFA, restoration. |
| A8 | Design (minor) | Auth text-field corner radius 6 vs `/m` 12; focus ring violet (app-wide token) vs `/m` raspberry; page gutter 16 vs `/m` 20; card padding 24 uniform vs `/m` 28/24/24. | **NOTED-DEFERRED** — field style is shared by MFA/password-reset/restore; change all four screens together in the auth-design slice. |

## Auth — other screens

| Screen | Status |
|--------|--------|
| RegistrationView / VerificationCodeView | BY-PLAN (no `/m` counterpart). OPEN: design-consistency check against the new login card treatment. |
| MultiFactorView / PasswordResetFlow / RestoreAccountFlow | BY-PLAN. OPEN: same design-consistency check. |
| FaceIDOptInView / LockedView | BY-PLAN (Package 3). OPEN. |

## Dashboard (`/m /dashboard` → `Features/Dashboard/DashboardView.swift`)

Content parity is largely present (level wheel + "X of Y actions" + next-level
copy + percentile, next-milestone nudge, focus areas with actions +
mark-complete + fyn_capture routing, Today's insight, module summaries,
offline-stale banner, error-retry). Deltas:

| # | Axis | Mismatch | Disposition |
|---|------|----------|-------------|
| D1 | Functionality | `/m` lets users skip a recommendation for the session (reappears when none left) — native had no skip. | **FIXED** — session-local skip in `FocusAreasView` mirroring `/m` `skippedIds`/`visibleActions`. |
| D2 | Functionality | `/m` has "View all {module}" + "See all actions" links under the focus actions — native had neither. | **FIXED** — both links added, routed like `/m`. |
| D3 | Detail | Native invents a "Your financial plan / Your dashboard, actions and progress in one place." header; `/m` shows a time-of-day greeting ("Good morning, {name}") in a fixed header with the menu button. | **NOTED-DEFERRED** — header belongs to the visual-reshape slice (D7). |
| D4 | Functionality | `/m` mark-done is an optimistic toggle with silent dashboard refetch and revert-on-failure; native is a one-way "Mark complete". | **NOTED-DEFERRED** — same endpoint, different affordance; fold into D7 or accept. |
| D5 | States | `/m` loader is the branded £-coin spinner; native uses the generic `LoadingView`. | **NOTED-DEFERRED** — D7. |
| D6 | Detail | Dashboard payload `alerts` and `new_milestones` decoded but not rendered natively; `/m` shows a milestone toast (Share/Dismiss) and the level-up fireworks takeover (`GamificationCelebration`), plus Fyn nudge bubbles (finish-tax-plan, KYC unlock). | **OPEN** — milestone toast + celebration + nudges are bounded builds; alerts render is small. Part of the fix queue unless CSJ folds into D7. |
| D7 | Design/intent | The `/m` dashboard's visual architecture — gradient hero, "LEVEL UP" callout + carousel, finances viz grid, Fyn dock, icon drawer. | **FIXED (CSJ approved rebuild 2026-07-20)** — four slices landed: S1 greeting header (time-of-day + first name), 135° horizon→raspberry hero with translucent level card + milestone nudge, raspberry callout with percentile + `TabView` paging carousel (dots + chevrons) + `/m` action rows (check-toggle, key/bulb glyphs, chevron, session skip), £-coin loader, milestone toast (Share/Dismiss), alerts rendered; S2 "Your finances" 5-panel grid mirroring `/m` `finances()` (horizon/raspberry/spring/violet tones, donut + bar viz; Spring100/500 + Horizon100 colorsets added; `ModuleSummaryView` deleted — its "Estimated IHT" violation went with it; `net_worth.trend` decoded additively); S3 Fyn dock bar ("Fyn / Your financial companion" + chevron; **the /m dock's Fyn avatar was NOT ported** — mascot-as-inline-icon stays banned, flag to CSJ if wanted); S4 drawer-parity menu (icons per `/m` NAV_ICON via SF Symbols, user name/email header, Share Fynla, Lock + Sign out via `SettingsModel`). |
| D8 | States | Level-up fireworks takeover (`GamificationCelebration`) and Fyn nudge bubbles (finish-tax-plan, KYC unlock) still have no native equivalent. | **NOTED-DEFERRED** — bounded follow-on builds; SSE `level_up` is already parsed by the Fyn reducer. |
| D9 | Functionality | Pre-existing regression found: an earlier session removed the unlocked shell's Lock/Sign-out affordances (P3 contract; `PrivacyLockControllerTests` red at baseline). | **FIXED** — Lock + Sign out restored in the drawer menu wired through `SettingsModel` (single sign-out path incl. push unregister); contract test reconciled to pin the new chain. Local-only StoreKit hosted-config reds (6 tests, `productUnavailable` off-device buy mode) remain environment-dependent — green in CI per pkg4 evidence. |

## Modules — all OPEN pending sweep

Dashboard · ModuleDetail equivalents (Income, Expenditure, Net Worth + category
+ history, Savings + account, Investment + account, Retirement + pension,
Protection + policy, Estate, Goals) · Tax Strategy · Holistic Plan ·
Achievements · Fyn · BugReportSheet · Navigation menu vs `/m` route groups.

Known candidates already identified by the 2026-07-20 audit (to fix during the
sweep): invented Savings interest rows + runway line; Retirement DC
contribution + income-gap recompute; Protection annual premium; dashboard
alerts decoded-not-rendered; "Estimated IHT" copy; Estate full-mode thinner
than `/m`; `.module` route dev-stub screen.

## Settings / Privacy / Subscription — BY-PLAN (Package 7), content sweep OPEN

## 2026-07-20 evening — CSJ "wrong path" answer + true-/m rebuild (this session)

CSJ direction: the native screens MUST match `/m` in look and functionality —
it is a copy task; write it in Swift and make sure the APIs connect. The delta
had stayed too big because screens were rebuilt from summaries of `/m` rather
than transcribed from `resources/mobile/` source and verified against the LIVE
`/m` rendering (csjones). This pass transcribed directly and verified against
real `/m` screenshots side-by-side on the open simulator.

| # | Axis | Item | Disposition |
|---|------|------|-------------|
| E1 | Design | Shell chrome: gradient app bar (translucent circular hamburger + white greeting) painting the top slice of the shared 352pt 135° horizon500→raspberry500 gradient; left slide-in drawer (86% ≤320pt, backdrop rgba(15,23,42,.55)); full-width white Fyn dock flush to the bottom (avatar image, name/status, circled up-chevron, hairline top border); Fyn overlay as full-screen slide-up. Old toolbar "Menu"/"Settings" text buttons deleted. | **FIXED** |
| E2 | Design | Drawer transcribes `/m` md-drawer exactly: name/email head + eggshell circular close, uppercase group labels, 20pt icons, LightBlue100 active pill, raspberry Sign out. Invented Achievements/"Report a problem" menu entries removed (achievements = level wheel/"See all actions"; report = Fyn header, both as on `/m`). Settings + Lock kept in the account section as native necessities. Admin section gated on the new `is_admin` decode (opens web admin). | **FIXED** |
| E3 | Design | Level card: 140pt wheel (horizon100 track / raspberry arc, 100pt horizon600 inner "LEVEL n"), translucent blurred white card; `/m` box model reproduced literally (card −144 bottom margin, nudge +8 overlapping under the wheel, callout +128 top margin, gradient capped at span) — verified against live `/m` (nudge really does overlap the card). | **FIXED** |
| E4 | Design | LEVEL UP callout: raspberry gradient top (LEVEL UP 26/900 + percentile copy), LightPink50 lower half, eggshell focus cards (active raspberry border/copy, locked LightGray + LOCKED pill), white circular arrows + raspberry capsule dots, `/m` action rows (52pt check zone + LIGHT hairline divider — live `/m` shows light grey, not the CSS-fallback dark; done = savannah100 + spring; unlock = dotted horizon300 + grey key; raspberry bulb/chevron; dismiss X zone). | **FIXED** |
| E5 | Design | Finances grid: centred tiles, per-tone 135° gradient tints, 68pt conic pie donut with 52pt white inner (num + uppercase cap), bar variant, uppercase 11pt labels with small glyphs, whole-pound values (`gbpWhole` matching `/m` fmt()); eager rows (no LazyVGrid). | **FIXED** |
| E6 | Design | `/m` Fyn surface: header (avatar, companion status, Report a problem, circular X), white fyn bubbles with horizon100 hairline, raspberry-outline reply pills (wrap layout), composer (avatar + "Ask Fyn anything..." bordered input + raspberry send square). Native "Do not include passwords…" disclaimer removed (not on `/m`); Stop-streaming control kept in the send slot (native necessity, `/m` has none). User-bubble styling left as light horizon fill — `/m` defines no user style; verify on a live capture turn during the sweep. | **FIXED** |
| E7 | Functionality | Share endpoints wired: drawer "Share Fynla" → GET `/api/v1/mobile/share/app_referral`; milestone toast Share → `share/{share_type}` — native share sheet presents server copy (was hardcoded strings). | **FIXED** |
| E8 | Functionality | REGRESSION FOUND + FIXED: the header's clipped 352pt gradient background remained hit-testable over the hero — the level wheel and milestone nudge were dead tap zones. `allowsHitTesting(false)` on both phantom gradient layers; level-wheel → Achievements journey verified green. | **FIXED** |
| E9 | Functionality | Fyn cover dismiss-then-navigate race: routes requested inside the cover (Report a problem, fyn navigation) now apply in `onDisappear`, so the push no longer lands under a lingering modal. | **FIXED** |
| E10 | Functionality | Dock clearance: `/m` pages end with md-bottom-pad (5rem) clearing the dock. Dashboard tail set to 80pt; BugReport given the same (its submit button was unreachable under the dock on SE) + keyboard Done toolbar. **Sweep note: every pushed module screen needs the same 80pt bottom clearance.** | **FIXED here; sweep item for module screens** |
| E11 | Design | Fyn dock avatar ported (Image asset `FynAvatar` from `/m`'s Fynla-Fyn-Icon.png) per CSJ's "design must match /m" direction — supersedes D7-S3's flag. Also used in the Fyn header + composer as on `/m`. | **FIXED (CSJ-directed)** |
| E12 | States | `/m`-only surfaces observed live and NOT ported this pass: onboarding "Finish your personalised tax plan with Fyn" nudge + KYC unlock bubble (needs native onboarding-state plumbing — sweep); GamificationCelebration fireworks (D8 stands); csjones-only floating bug-report FAB (env-specific, do not port). Dashboard alerts section (native invention, not on `/m`) REMOVED. | **OPEN (sweep) / alerts removal FIXED** |

Verification: unit suite green except the 6 pre-existing local StoreKit reds;
journey UI tests green (shell fixtures, level-wheel→achievements,
Fyn conversation, Fyn→report-a-problem full submit, drawer→settings Face ID);
ParityScreenshotTests harness captures side-by-sides; live `/m` reference
screenshots taken on csjones (login + MFA via tinker) for header/hero/callout/
drawer/Fyn and matched.

## 2026-07-20 evening — audit P0 dispositions under the copy-/m ruling

| # | Audit item | Disposition |
|---|-----------|-------------|
| P0-1 | Savings interest computed in Swift (`SavingsAccountView`) | **KEEP — /m parity.** `/m` computes the identical figures client-side: `SavingsAccount.vue:119-123` (`annualInterest = balance × rate / 100`, `monthlyInterest = annual / 12`). |
| P0-2 | Emergency runway computed client-side (`SavingsView`) | **KEEP — /m parity.** `Savings.vue` `runwayMonths()` = `totalCash / monthlyExpenditure`, same rounding (`runwayLabel`). The server's `emergency_fund_months` feeds the dashboard tile, not this screen — same as `/m`. |
| P0-3 | DC monthly contribution from percentages × salary (`RetirementModels`) | **KEEP — /m parity.** `RetirementPensionDetail.vue:190-199` derives it identically, commented "Mirrors RetirementProjectionService". |
| P0-4 | Income gap recomputed client-side (`RetirementModels`) | **KEEP — /m parity.** `Retirement.vue:186-190` computes `targetIncome − projectedIncome` client-side with the same analysis/profile fallbacks. |
| P0-5 | Protection annual premium ×12/×4 incl. unknown→×12 (`ProtectionModels`) | **KEEP — /m parity.** `ProtectionPolicy.vue:203-209` has the same switch INCLUDING `default: amount * 12`. The "weekly understated" quirk exists on `/m` too — fixing it is a `/m`+native change, out of native scope. |
| P0-6 | Hardcoded 35 National Insurance years fallback (`RetirementPensionView`) | **KEEP — /m parity.** `RetirementPensionDetail.vue:96` renders `pension.ni_years_required || 35` — the same fallback. |
| P0-7 | AI-chat consent toggle + required consents as live switches (`PrivacySettingsView`) | **FIXED.** The ai_chat toggle is deleted (settled contract: consent at registration, NO UI toggle); terms/privacy/data-processing render as display-only "Agreed at registration" rows; marketing remains the single revocable toggle. Privacy tests green. |
| P0-8 | "Estimated IHT" on the dashboard estate card | **ALREADY FIXED** — died with `ModuleSummaryView` in the dashboard rebuild; no occurrence remains in `ios-native`. |

Note: items P0-1…P0-6 were written against the migration contract's
"no financial calcs in Swift" before CSJ's 2026-07-20 five-axis copy-/m
direction. The native code transcribes `/m`'s own display-layer computed
properties; making them server-side would change `/m` too and is a
backend/`/m` decision, not a native one.

## 2026-07-20 evening — audit P1 dispositions

| # | Audit item | Disposition |
|---|-----------|-------------|
| P1-1 | `verify-project.sh` always exits 1; no CI invokes it | **FIXED.** Legacy-dependency greps now match real dependencies only (`import Capacitor/WebKit` — with `LegacyCapacitorCleanup.swift` as the sanctioned WebKit exception — and pod/Capacitor SDK artefacts in project files). Script verified exit-0 locally; wired into `.github/workflows/ios-native.yml` as the first step after checkout. |
| P1-2 | Strict enum decoding kills whole screens | **FIXED.** `DashboardModuleStatus`→`.unavailable`, `DashboardAlertSeverity`→`.info`, `DashboardActionType`→`.recommendation` (matches `/m`'s `=== 'unlock'` else-branch), `DashboardActionKind`→new `.unknown` (tap is a no-op, as `/m`), `EstateMode`→`.teaser`. Unit-covered (`unknownEnumValuesDecodeToSafeFallbacks`). |
| P1-3 | Retirement silent failures (`try?` analysis/projections) | **SPLIT.** Analyze failure is silent on `/m` too (`Retirement.vue` only sets `analysisReady` on success) — KEEP. Projections failure now shows `/m`'s exact copy "Projections are not available right now." (`snapshot.projections == nil` ⇔ fetch failed); success-with-no-pot keeps "No projection available yet.". |
| P1-4 | Data-export permanent spinner after 6 polls | **FIXED.** New `DataExportState.stillPreparing` + retry card ("taking longer than expected"); unit-covered with a never-completing stub. |
| P1-5 | Write 401s never refresh the token | **FIXED.** Mutating requests still never replay, but a 401 now triggers one token refresh so the user's immediate retry succeeds. Contract test renamed and updated (`neverReplaysWritesAfter401ButRefreshesForTheNextAttempt`). |
| P1-6 | Balance-history keyed on magic `windowDays == 90` | **KEEP — /m parity.** `BalanceHistory.vue:85` is literally `window_days === 90` (`isFreeWindow`). |
| P1-7 | "ISA allowance used / of £20,000" reads broken | **KEEP — /m parity.** `Savings.vue:109-110` renders the same split row (label left, "of £X" right). |
| P1-8 | Money format app-wide | **SWEEP ITEM (new).** `/m` modules share `formatCurrency` with `maximumFractionDigits: 0` (whole pounds) while native module screens use 2-dp `MoneyFormatter.gbp` — align per screen during the module sweep. |
| P1-9 | Diagnostics layer dead code | **DEFERRED with note.** Where to `record()` is a design decision (candidates: API failures, decode fallbacks); not user-facing. |
| P1-10 | `.module` route dev stub; legacy `SubscriptionPlanSeeder` plans | **SWEEP / backend-cleanup notes.** |

## 2026-07-20 late evening — module sweep, block 1 (chrome + Cash Management + Achievements)

| Item | Disposition |
|------|-------------|
| Shared MobileChrome shell | **DONE.** The shell owns /m's fixed gradient app bar (hamburger + greeting) on every screen; system navigation bars hidden app-wide (as /m); dock + drawer persist across pushes. `MobileChromeScaffold` provides the gradient metrics, self-anchoring slice, `MobilePageHero` and `MobilePageActions` (Back / Edit details pills). Two shell layouts were tried and rejected on evidence (GeometryReader+ignoresSafeArea broke stack hit-testing; safeAreaInset header broke scroll insets) — plain VStack shell verified correct. |
| Income | **DONE.** Transcribed from Income.vue: hero card + per-source rows (employment detail), whole pounds, Edit details generic prompt, £-coin loader, dock clearance. /m's spouse-verify query-param view (`?section=spouse`) has no native counterpart (onboarding-verify plumbing) — sweep note. |
| Expenditure | **DONE.** Transcribed from Expenditure.vue: monthly hero + annual line (with /m's ×12 fallback), category rows, whole pounds. |
| Achievements | **HEADER DONE.** /m "Your progress" hero + Back pill; tabs/content sweep still open. |
| Known gaps carried forward | (a) Page hero should persist across loading/error states as /m's MobileChrome does — currently state views replace it. (b) `ScreenStateView`/`ErrorView` are stock-styled (system-blue buttons) — restyle to /m's error card + raspberry "Try again". (c) Income/Expenditure UI-test compositions are unstubbed (screens show the error state in fixture mode) — visual content verification needs stubs or a live staging login. (d) Remaining 10+ screens per the sweep list. |

## 2026-07-20 night — CSJ direction: milestone banners never obscure the level hero

Supersedes the E3 note that transcribed /m's nudge-overlaps-card box model.
CSJ ruled the overlap wrong on BOTH surfaces: the "Next milestone" nudge now
flows BELOW the level card (9rem top margin clearing the card's -9rem
overflow; callout clearance margin applies only when no nudge is present),
and the "Milestone reached" toast floats below the card (top 25rem) instead
of over the wheel. Implemented in /m source (dashboard.css + Dashboard.vue,
main repo, needs build+deploy) and native (DashboardView), native verified by
manual fixture launch screenshot + journey suite.

## 2026-07-21 — module sweep, block 2 (Net Worth → Retirement) + dark hero correction

| Item | Disposition |
|------|-------------|
| Net Worth | **DONE** — category rows, balance-history card, DB note, liabilities row (raspberry value). |
| Savings | **DONE** — account/ISA rows (spring emergency-fund tag, rate), cap head + Upgrade, status-coloured runway + ISA allowance bars per /m thresholds. |
| Investments | **DONE** — risk row, violet ISA tag, holdings meta, cap head + Upgrade. |
| Protection | **DONE** — severity-tagged gaps (Low/Medium violet on light blue, High white on raspberry), " p.a." detail, /m empty copy; native-only recommendations card REMOVED (not on /m). |
| Goals | **DONE** — on-track hero, overall-progress card, status pills/bars per /m's exact thresholds, ADD GOAL/EDIT via Fyn with /m's prompts. |
| Estate | **DONE** — teaser (liability hero + Premium note + Compare plans pill) and full mode (breakdown with bordered net-estate total, coloured will status); Edit pill wired via the factory. |
| Retirement | **DONE** — dark hero with target/surplus split (spring-400/raspberry-300), pension rows, Overview + projection rows with the /m age-source note, bordered recommendation cards. |
| **Dark m-hero correction** | style.css truth: `.m-hero` = horizon-500 card, white 44pt/900 metric, light label/sub. Shared `MobileHeroCard` adopted across all converted screens (the earlier white heroes were wrong). Section labels corrected to neutral500 (#6B7280 on /m). Raspberry300 token added. |
| Still open | Tax Strategy, Holistic Plan, sub-pages (savings account, investment account, pension detail, protection policy, net-worth category, balance history), Achievements content, hero-persistence across loading/error states, Income/Expenditure fixture stubs, onboarding nudge + fireworks, user chat-bubble check. |

## 2026-07-21 — module sweep, block 3 (Tax Strategy)

| Item | Disposition |
|------|-------------|
| Tax Strategy | **DONE** — transcribed from TaxStrategy.vue: personalised intro (CSJ 3.2; gated on `onboarding_completed` via new `AuthenticatedUser.onboardingCompleted` → `SettingsModel`), household card (heading label + CGT/IHT qualification + household recs), recommended actions (Watch-out flag, Saves chip spring-600 18/900, next-step CTAs incl. /m's literal "Open investment", Mark-as-done pill, adviser tag), Done group (`Done dd/mm/yyyy` from `toIso8601String()`), dark headroom hero with spring-600 metric (`.mts-available`; `MobileHeroCard` gained an optional `metricColor`), status-coloured allowance bars (spring/violet/raspberry fills; muted = track only, no `mts-bar__fill--muted` rule on /m; remaining label spring-600/violet/raspberry/neutral), spouse allowances card, raspberry back-to-dashboard button. Verified against LIVE /m (csjones, john@example.com, 375px): screenshots matched section-by-section. |
| Mark-as-done pill text colour | **Live-rendering call.** /m's `.mts-rec__done`/`.mts-rec__done-tag` use `var(--spring-700)` which is UNDEFINED in style.css → colour computes to the inherited body `#1F2A44` (horizon-500) with the spring-500 border. Native matches the rendered output, not the dead variable. |
| Tax Strategy fixture stub | **NEW.** `TaxStrategyUITestComposition` (household mode, all four allowance statuses, warning + done items) wired in the unlocked composition; ParityScreenshotTests now captures 4 tax-strategy frames (precise drag so the dark hero clears the dock-occluded band). Test-mode user gained `onboardingCompleted: true` so the intro renders in captures. |
| Settings Sign out under the dock (found while looping) | **FIXED (app bug).** SettingsView had no dock bottom-clearance pad, so at full scroll Sign out sat permanently under the Fyn dock on small devices; `bottomClearance` pad added. The journey test's Lock/Sign-out hittability asserts now scroll with quarter-screen drags (a full `swipeUp` overshoots the mid-page Lock button). |
| /m-only states on this page | Onboarding verify Continue/Edit bubbles replace the Edit-details pill for mid-onboarding users (seen on live csjones) — deferred with the onboarding nudge item; csjones bug-report FAB not ported (standing rule). |
