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
| D7 | Design/intent | The `/m` dashboard's visual architecture — gradient hero containing the level wheel, "LEVEL UP" callout card containing percentile + swipeable focus carousel (arrows/dots), "Your finances" as a 4-panel grid with mini-viz (bar/dial) rather than a card list, docked Fyn bar opening an overlay chat (vs native Fyn as a menu destination), drawer menu with icons/groups/user header/Share Fynla/Sign out (vs native text-only menu screen), key/bulb action glyphs, checkbox toggles, chevrons. Package 5 plan sanctioned "text labels; no invented tab bar" adaptations, but CSJ's 2026-07-20 direction is that design must match `/m`. | **NOTED — needs CSJ sizing call.** This is a multi-day rebuild of DashboardView + FocusAreasView + ModuleSummaryView + navigation/Fyn surface patterns. Awaiting triage: rebuild now, schedule as its own slice, or accept the plan-sanctioned adaptation. |

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
