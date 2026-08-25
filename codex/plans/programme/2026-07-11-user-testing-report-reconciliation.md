# User Testing Report Reconciliation

**Date:** 11 July 2026

**Source:** [Google Drive — Testing 1/5/26 onwards](https://docs.google.com/document/d/1rQAgOR7KTocntyt9l5AXzIJRiA3KwoBkhSLOFu9wnio)

**Document ID:** `1rQAgOR7KTocntyt9l5AXzIJRiA3KwoBkhSLOFu9wnio`

**Revision reviewed:** `ALtnJHwFove9vFBwb0swbbomT3l2IUyrgLUqXvszSAgI9qHVaJJdph8g1KRHT650bTEim3GQn5FZgo1rSHjagf0G5cgTrS_zbZJZ7QOHhCk`

**Last modified:** 10 July 2026 at 09:43:30 UTC

**Purpose:** Reconcile the May-to-July user report against the current `dev` code and the Fynla Online Readiness Programme. The source report is evidence, not an implementation specification: each observation is reproduced, mapped to current code, and either made a launch acceptance criterion, retained as a regression check, or placed behind an explicit product/copy decision.

## 1. Reconciliation rules

- A user-observed silent write, duplicate registration, fabricated value, misleading financial state, or inaccessible journey is a pre-launch issue.
- A behaviour already fixed in current code remains a browser regression scenario; the report predates some July fixes and does not prove current failure by itself.
- Suggested commercial or stylistic copy is adopted only where it improves factual clarity or accessibility. Pricing claims are computed from live configuration rather than hardcoded.
- SaveTax may ask about income, pensions, spouse circumstances and expenditure when those inputs materially change the tax strategy. The journey must explain why the information is needed, allow sanctioned skips, and never invent missing data.
- Existing icons are not expanded. Any new public-page icon remains subject to CSJ approval under `AGENTS.md` Rule 15.

## 2. Finding map

| ID | User observation | Current-code evidence | Disposition | Programme mapping |
|---|---|---|---|---|
| UT-01 | A signed-in user was asked to register again on a public URL. | Live SaveTax routes now use `redirect.authed`; `RedirectAuthenticatedToDashboard` adds a bearer-token client guard, but there is no focused campaign regression test. | Reverify before launch on desktop and `/m`; fix if the browser scenario remains red. | Task 10A |
| UT-02 | Tax-year and number typography looked wrong. | The current plan page has separate campaign typography and a JS-rendered allowance grid. Visual correctness is not covered by an automated snapshot/contrast check. | Pre-launch visual/accessibility acceptance. | Task 10B |
| UT-03 | The dashboard should offer a contextual “Ask Fyn how you can save tax” action. | Campaign affinity and next actions exist, but this report does not prove a correctness failure. | Product-polish decision after the blocking journey is green; retain in the report register, not as a release blocker. | Task 29 decision record |
| UT-04 | Too many Register actions appeared on one screen. | `savetax-plan.php` contains the compact form plus matching calls to action above and below the allowance grid. | Pre-launch content-hierarchy pass: one primary action per viewport/section and no competing duplicates. | Task 10B |
| UT-05 | Fyn asked for children’s ages and stored `1 January` dates of birth. | Both `OnboardingChatDirector::createDependantFamilyMembers()` and `CoordinatingAgent::handleCaptureDependants()` manufacture `startOfYear()` dates. `ChildrenDOBFallbackTest` currently pins the inaccurate behaviour. | High-severity data-accuracy defect. Ask for exact dates of birth; age-only input must not create a precise date or a completed child record. | Task 10A; evidence-first memory accuracy contract |
| UT-06 | It was unclear whether a spouse account was mandatory. | `STATE_BASE_SPOUSE` has “Skip this for now” and `SpouseSkipTest` verifies server advancement. | Already implemented; reverify visibility, keyboard access and `/m` parity. | Task 10A regression |
| UT-07 | SaveTax asked about expenditure and retirement. | These are deliberate campaign inputs; pension states already skip when no pension was selected, while expenditure affects affordability and strategy. | Keep only when relevant, explain why, and never block the whole journey when a sanctioned skip is available. | Tasks 8, 10A |
| UT-08 | Expenditure was confirmed while the screen remained on personal details. | Current code writes both `users.monthly_expenditure` and `expenditure_profiles.total_monthly_expenditure`, then enters a profile-review state; the report predates these tests. | Reverify DB, stream event, review panel and navigation together. No success copy without all four. | Tasks 10, 10A |
| UT-09 | The user challenged the incorrect child date and did not accept the approximation. | Same root cause as UT-05. Notes describing an inference do not make a fabricated date accurate. | Same blocking fix as UT-05; do not preserve the old fallback. | Task 10A |
| UT-10 | An open Fyn window blocked page viewing/scrolling unless collapsed. | Desktop and `/m` use different chat shells and responsive state. Current launch plan covers event parity but not underlying-page usability. | Add responsive browser acceptance: collapse/dock preserves the turn, restores page scrolling and never traps focus. | Tasks 10, 10A, 24 |
| UT-11 | A £10,000 expenditure entry was not visibly reflected. | Backend synchronisation now exists, but visible readback must be proven on both surfaces. | Treat as silent-success regression: DB, API, stream and displayed value must agree. | Tasks 10, 10A, 15 |
| UT-12 | “Fyn is ignoring me.” | Task 10 already covers typed errors, capture events and surface parity; Task 9 covers loop failures. | Covered root-cause family, with this report added to the live browser corpus. | Tasks 9, 10, 10A, 22J |
| UT-13 | Fyn appeared to know an ISA was Stocks & Shares without being told. | The deterministic extractor classifies an explicit Stocks & Shares phrase, but a bare ISA is currently named as a Cash Individual Savings Account; neither path has an ambiguity confirmation contract. | No subtype inference. Bare “ISA” pauses for Cash/Stocks & Shares/Lifetime confirmation before persistence. | Task 10A |
| UT-14 | The journey did not ask whether assets were joint. | Asset capture can persist individual records without an ownership confirmation. Joint ownership materially changes household calculations and authorization. | Ownership is a required confirmation for newly captured household assets; writes wait until individual/joint and owner/share are known. | Tasks 10A, 16 |
| UT-15 | Existing-account registration text had poor contrast. | The compact form now injects an inline hardcoded `#E6C9A8` error and still provides no actual sign-in control. | Replace with palette CSS, WCAG contrast proof and a visible sign-in link. | Task 10B |
| UT-16 | Homepage copy should say allowances users may be “missing out on”. | Current `index.php` says “allowances you could be missing.” | Adopt the clearer wording in the public-copy pass. | Task 10B |
| UT-17 | The savings result needs “of up to” wording. | Current hero separates “Up to” from the figure and “average estimated saving each year.” | Rewrite as one unambiguous sentence, e.g. “An average estimated saving of up to £X each year.” | Task 10B |
| UT-18 | The result must clearly say it is an average, not the user’s personal saving. | Current disclaimer is directionally correct but shorter than the user-tested wording. | Adopt the explicit average/not-personal disclaimer and regression-test the rendered claim. | Task 10B; Consumer Duty clarity evidence |
| UT-19 | Pension Annual Allowance appeared when no pension was selected. | `pensionAaItem()` intentionally shows the opportunity for earners/non-earners even without an existing pension. | Keep the opportunity but explain that eligibility can exist without a current pension and that contribution limits depend on earnings and circumstances. | Task 10B |
| UT-20 | A grey Personal Allowance card looked unavailable or already used, with no clear meaning. | `personalAllowanceItem()` greys employed users because the allowance is automatically used, while page copy says all grey cards “don’t apply.” | Financial-clarity defect. Replace binary on/off semantics with `available opportunity`, `used automatically`, and `not applicable`, rendered as plain text with accessible colour/contrast. | Task 10B |
| UT-21 | Annual pricing should say “Save 17%”. | Current fallback annual price is ten monthly payments, but prices are loaded from `/api/pricing-config` and can change. | Show a dynamically calculated, rounded saving only when annual is genuinely cheaper; never hardcode 17%. | Task 10B |
| UT-22 | SaveTax registration asked for the same name/email/password twice on desktop and mobile. | Compact registration posts first, then relies on `sessionStorage('fynla_pending_verify')` surviving a full navigation to `Register.vue`; failure falls back to the full form. | Critical conversion/auth continuity defect. Replace browser-storage handoff with a short-lived server-issued opaque token and open verification directly. | Task 10A |
| UT-23 | Restoring an old account led to an outdated/wrong pricing-plan screen. | Compact registration redirects restorable accounts to `/register` without campaign state or restoration data. | Preserve campaign source through restoration, open the existing restore modal directly and route the restored user to the correct signed-in destination, never a stale pricing choice. | Task 10A |
| UT-24 | The desktop SaveTax result page was too wordy. | The page repeats explanatory and conversion copy across the hero, allowance explanation and two matching calls to action. | Pre-launch copy hierarchy pass without deleting required claim qualifiers or risk context. | Task 10B |
| UT-25 | Existing users need an obvious sign-in action during compact registration. | The full `Register.vue` has a link; the compact SaveTax form only renders error text. | Add a real, keyboard-accessible sign-in link that preserves `from=savetax`. | Task 10B |

## 3. Pre-launch outcomes

The report adds two mandatory work packages to Gate 2:

1. **Task 10A — User-journey truth and capture integrity.** Registration/verification/restoration continuity, exact child data, ambiguous ISA and joint-ownership confirmation, visible expenditure writes, chat usability, and desktop/`/m` browser evidence.
2. **Task 10B — SaveTax claim, allowance and conversion clarity.** Accurate average-saving language, three-state allowance semantics, pension-opportunity explanation, accessible errors/sign-in, CTA hierarchy, typography, and dynamically calculated annual saving.

UT-03 remains a product-polish decision in Task 29 because it does not block correctness. Every other “already fixed” observation remains in the acceptance corpus so a code-level fix cannot be counted green without reproducing the user journey.

## 4. Required evidence

- A machine-readable `user-testing-register.yaml` row for UT-01 through UT-25, including status, programme task, automated tests and live-browser evidence.
- Desktop and `/m` Playwright coverage for fresh campaign registration, existing email, restoration, verification, signed-in campaign access and responsive Fyn behaviour.
- Database assertions for pending registration, user creation, funnel source, dependant data, asset ownership/type, expenditure profile and audit trail.
- Accessibility checks for contrast, focus order, keyboard sign-in/restoration, modal focus return and no scroll trap.
- Copy snapshots for the homepage claim, SaveTax estimate/disclaimer, allowance-state explanation and calculated annual saving.
- Redacted csjones screenshots/network/DB evidence attached to the readiness ledger before Gate 2 can turn green.
