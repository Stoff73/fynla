# Save Tax and Pension Check campaigns — application map

| | |
|---|---|
| Scope | The two marketing campaigns as they exist in code: the public Save Tax and Pension Check funnels and result pages, the compact registration and encrypted hand-off, how a campaign is chosen when Fyn starts, the campaign-only states of the onboarding state machine (savetax and pensioncheck section walks, per-section advice, synthesis, spouse invitation, terminals), the campaign capture tools and their write handlers, campaign re-entry for completed users, and the Tax Strategy page the Save Tax walk lands on (web, `/m`, iOS). Out of scope: the base onboarding states and the verify loop mechanics (map 02a), the thirteen tax strategy calculators and `TaxStrategyMath` (section 13, UK tax), the Marketing Pipeline "campaigns" table used by the admin content tools (a different concept, section 16), the lifecycle email campaigns (section 17), the `/biggerpension`, `/paymortgage`, `/managedebt` and `/wealth` Vue pages (they are not campaigns in this sense: § 2.1). |
| Commit | `6b365dddc` on `dev` |
| Mapped on | 2026-09-14 |
| Mapped by | Claude Code session 3, 2026-09-14 |
| Supersedes | none (INDEX row 02b was "not started") |
| Issues raised | MB-37 to MB-49 (in `September/September14Updates/mappingBugs2026-09-14.md`) |

## 1. Overview

### In plain English

A campaign is a paid or social advert that sends a stranger to a short questionnaire on the public site: five questions for Save Tax, six for Pension Check. The answers produce an illustrative estimate on a results page, next to a four-field sign-up form. Registering from that form carries the answers into the new account, so when the person lands on their dashboard Fyn already knows their employment, income band, spouse situation and what they hold, greets them with a recap, and walks them through only the sections that apply. At the end of the Save Tax walk Fyn voices a ranked tax plan and sends the person to the Tax Strategy page; the Pension Check walk ends on the Retirement page. A person who has finished onboarding can come back into the Pension Check walk later; the Save Tax walk can only be done once.

### How it fits together

The public pages are server-rendered PHP files under `public/pages/` with their own JavaScript; they call one Laravel service each to compute the estimate from the answers. The sign-up form posts to the ordinary registration endpoint with an extra `funnel_answers` object, which is validated, stamped with the tax-year context of the income band, stored on the pending registration, and copied to the user at verification. An encrypted, fifteen-minute hand-off token carries the person from the public page to the web app's verification screen without a second form. On the dashboard the web SPA and `/m` both call the onboarding start endpoint with the campaign name; the controller chooses the campaign from the URL, a paused walk, or the stored funnel answers, and the onboarding director drives the walk over the single chat endpoint every surface uses. The campaign states live in the same state machine and corpus file as base onboarding; each section ends in the verify loop mapped in 02a, then an advice turn that reads the composed tax plan (savetax) or composed retirement plan (pensioncheck). Captures are written by named handlers in the coordinating agent, mostly into the ordinary module tables, plus one campaign-specific table for spouse household figures. The Tax Strategy page reads a calculator payload and the composed plan; all three clients call the same two endpoints.

### Flow diagrams

- `docs/diagrams/map-campaigns-funnel-to-fyn.excalidraw` — the public funnel, estimate page, compact registration, hand-off token, verification and the first Fyn turn.
- `docs/diagrams/map-campaigns-start-and-reentry.excalidraw` — how the onboarding start endpoint chooses a campaign, resumes a paused walk, or re-enters a completed user.
- `docs/diagrams/map-campaigns-savetax-walk.excalidraw` — every state of the Save Tax section walk in order, with the skip conditions.
- `docs/diagrams/map-campaigns-pensioncheck-walk.excalidraw` — every state of the Pension Check section walk, including the re-entry recap.
- `docs/diagrams/map-campaigns-tax-strategy-terminal.excalidraw` — the Tax Strategy page request flow on web, `/m` and iOS.

### What it looks like

Web, driven this run (user 90, married, spouse with no income, savings + pension + ISA + investments ticked):

![Save Tax funnel, question 1](screenshots/02b-campaigns/web-funnel-01-employment.png)
![Save Tax funnel, income band](screenshots/02b-campaigns/web-funnel-02-income.png)
![Save Tax funnel, spouse income band (only shown after Yes)](screenshots/02b-campaigns/web-funnel-03-spouse-income.png)
![Save Tax funnel, assets multi-select with the only Continue button](screenshots/02b-campaigns/web-funnel-04-assets.png)
![Save Tax plan page: headline estimate, compact register form, allowance cards](screenshots/02b-campaigns/web-plan-01-full.png)
![Compact register form filled](screenshots/02b-campaigns/web-plan-02-register-form.png)
![Hand-off landed on /register: verification modal, no second form](screenshots/02b-campaigns/web-register-01-handoff-verify.png)
![Dashboard with Fyn open on the funnel recap](screenshots/02b-campaigns/web-fyn-01-funnel-recap.png)
![Income verify announce](screenshots/02b-campaigns/web-fyn-02-verify-announce.png)
![Income page during verify](screenshots/02b-campaigns/web-fyn-03-income-verify-page.png)
![Campaign consent gate](screenshots/02b-campaigns/web-fyn-04-campaign-intro.png)
![ISA captured, saved-record card](screenshots/02b-campaigns/web-fyn-05-isa-saved.png)
![Savings verify page: the two accounts just captured are not on it (MB-37)](screenshots/02b-campaigns/web-fyn-06-savings-verify-page.png)
![Investments verify page showing the Vanguard account](screenshots/02b-campaigns/web-fyn-07-investments-verify-page.png)

![Verify edit read-back on the investments page; the page itself still shows £25,000 (MB-47)](screenshots/02b-campaigns/web-fyn-08-verify-edit-readback.png)
![Pensions verify page](screenshots/02b-campaigns/web-fyn-09-pensions-verify-page.png)
![Spouse verify page: the Income page shows nothing about the spouse (MB-48)](screenshots/02b-campaigns/web-fyn-10-spouse-verify-page.png)
![Expenditure verify page showing £0 while the database holds £3,200 (MB-47)](screenshots/02b-campaigns/web-fyn-11-expenditure-verify-page.png)
![Synthesis and the spouse invitation](screenshots/02b-campaigns/web-fyn-12-synthesis-and-invite.png)
![Terminal: invitation declined, app note, celebration with the route button](screenshots/02b-campaigns/web-fyn-13-terminal.png)
![Tax Strategy page after the walk](screenshots/02b-campaigns/web-tax-strategy-01-full.png)
![Tax Strategy page after Mark as done](screenshots/02b-campaigns/web-tax-strategy-02-marked-done.png)

`/m`, driven this run (user 91, self-employed, in their 50s, personal and final-salary pensions, no spouse), at 390 px:

![Pension Check funnel framed inside the /m host](screenshots/02b-campaigns/m-funnel-01-framed-employment.png)
![Pension types multi-select](screenshots/02b-campaigns/m-funnel-02-pensions.png)
![Pension Check plan page: projected pot, register form, projection details, illustrative testimonials](screenshots/02b-campaigns/m-plan-01-full.png)
![Hand-off verification inside the frame](screenshots/02b-campaigns/m-register-01-handoff-verify.png)
![/m dashboard after verification with Fyn open on the Pension Check recap](screenshots/02b-campaigns/m-dashboard-01-after-verify.png)
![Pension Check recap turn](screenshots/02b-campaigns/m-fyn-01-pensioncheck-recap.png)
![/m income verify screen with the Continue and Edit pills](screenshots/02b-campaigns/m-fyn-02-income-verify-screen.png)
![After Continue: the date-of-birth question](screenshots/02b-campaigns/m-fyn-03-after-continue.png)
![/m retirement verify screen listing both pensions](screenshots/02b-campaigns/m-fyn-04-retirement-verify-screen.png)
![/m retirement goals verify screen](screenshots/02b-campaigns/m-fyn-05-goals-verify-screen.png)
![/m expenditure verify screen showing £3,067, not the £2,400 entered (MB-50)](screenshots/02b-campaigns/m-fyn-06-expenditure-verify-screen.png)
![/m synthesis and terminal](screenshots/02b-campaigns/m-fyn-07-synthesis-terminal.png)
![/m retirement page after the terminal](screenshots/02b-campaigns/m-retirement-01-after-terminal.png)
![/m re-entry: the existing-data recap](screenshots/02b-campaigns/m-reentry-01-existing-recap.png)

### Surfaces

| Feature | Web | `/m` | iOS | Notes |
|---|---|---|---|---|
| Save Tax funnel and plan page | Working | Working (framed in the `/m` host, code path identical) | Not present (public web page; the app has no funnel) | § 2.1, § 2.2 |
| Pension Check funnel and plan page | Unverified (not driven at desktop width) | Working (driven framed this run) | Not present | § 2.1 |
| Compact registration, hand-off, verification | Working | Working (verified token entered `/m/app/dashboard?from=pensioncheck`) | Not present (native registration is section 01) | § 2.3 |
| Campaign chosen at Fyn start | Working (`from=savetax`) | Working (`from=pensioncheck`, and the funnel fallback by code) | Unverified (native posts to the same endpoint; `FynClient.swift` read in 02a) | § 2.4 |
| Save Tax section walk | Working with MB-37, MB-46, MB-47, MB-48, MB-49, MB-54 | Unverified (not driven; the verify routes exist on `/m`, `router.js:63`) | Unverified | § 2.5 |
| Pension Check section walk | Unverified | Working with MB-50, MB-51, MB-52 | Unverified | § 2.8 |
| Verify edit ("No, change something") | Working after a rephrase (MB-49); page not refetched (MB-47) | Unverified | Unverified | § 2.5 |
| Advice turns and synthesis | Working | Working; paragraphs collapsed (MB-52) | Unverified | § 2.6 |
| Spouse invitation | Working (declined path only) | Unverified (single user this run) | Unverified | § 2.5 |
| Terminal and navigation | Working (`/tax-strategy`) | Working (`/m/app/retirement`) | Unverified (`AppRouter.swift:24, 55` maps `/tax-strategy`) | § 2.7 |
| Pension Check re-entry | Unverified | Working (recap, then the gap walk; MB-53) | Unverified | § 2.8 |
| Save Tax re-entry | Dead end (MB-44) | Dead end | Dead end | § 2.4 |
| Tax Strategy page | Working; next-step routes Dead end (MB-45) | Unverified (payload read; key drift MB-45) | Unverified (code read) | § 2.9 |
| Mark a strategy done | Working | Unverified | Unverified | § 2.9 |
| Tax Strategy recalculation | Dead (MB-42) | Dead | Dead | § 2.9 |
| Spouse figures on the Income page | Broken for a non-working spouse (MB-48) | Broken by code (same service) | Not present | § 2.10 |

### Depends on / depended on by

| Direction | Module or service | What crosses the boundary | Evidence |
|---|---|---|---|
| Consumes | Tax configuration (13) | every allowance and threshold on the funnel pages and in the estimates; the income band boundaries | `FunnelIncomeBand.php:137-189`, `SaveTaxEstimateService.php:551-612`, `TaxAllowancesController.php:36-98` |
| Consumes | Registration and sessions (01) | `POST /api/auth/register` accepts `funnel_answers` and `signup_source`; the verify step creates the user | `AuthController.php:67-166, 600-628` |
| Consumes | Onboarding base states and verify loop (02a) | `base_employment`, `base_work`, `base_employment_more`, `base_expenditure`, `campaign_verify_*` | `OnboardingStateMachine.php:189-262` |
| Consumes | Savings, Investment, Retirement (06, 07, 08) | the delegated capture states write ordinary module rows through `create_savings_account`, `create_investment_account`, `create_pension` | `OnboardingPromptBuilder.php:133-157`; audit rows 78-79 this run (`create_investment_account` persisted) |
| Consumes | Coordination (12) | `ComposedTaxPlanService` and `ComposedModulePlanService` (retirement) for the advice and synthesis turns; `RecommendationTracking` for mark-done | `OnboardingChatDirector.php:1252, 1329-1332, 1421-1423`; `TaxStrategyController.php:35, 88-91` |
| Consumes | Household and spouse linking (04) | the post-plan invitation goes through `SpouseLinkingService::linkOrCreateSpouse` | `OnboardingChatDirector.php:2107-2130` |
| Consumes | Gamification and `/m` dashboard (18) | milestone detection on the Tax Strategy read; activity feed labels per campaign state; campaign affinity ranks the tax module first for Save Tax arrivals | `TaxStrategyController.php:43-64`, `ActivityFeedService.php:43-50`, `NextActionsService.php:36-38, 266-278` |
| Consumed by | `/m` Income screen and web Income & Occupation | the spouse figures captured in `tax_strategy_household_inputs` are read back onto the spouse verify view | `UserProfileService.php:466-484, 498-505`; `resources/mobile/views/Income.vue:76-86`; `IncomeOccupation.vue:477-499` |
| Consumed by | Fyn context assembler (14) | the saved household finances are injected as a grounding block when the user asks about spouse finances | `FynContextAssembler.php:177-185, 537-542` |
| Consumed by | Phone redirect middleware | campaign paths are the only paths a phone visitor may be framed into `/m` with | `RedirectPhoneToMobile.php:43-45, 64-108`; `resources/views/mobile-host.blade.php:29-33` |

## 2. Detailed sections

### 2.1 The public funnels

**In plain English.** Two questionnaire pages live on the public site. Save Tax asks employment status, income band, whether there is a spouse, the spouse's income band (only if there is one) and which of six asset types the person holds. Pension Check asks employment, income band, age band, which pension types they hold, the pot size band and whether there is a spouse. Single-choice screens advance on tap; only the multi-select screen has a Continue button. The answers are kept in the browser and passed to the results page in the address.

**Status:** Working (Save Tax, driven on web this run); Unverified (Pension Check page, code read; driven on `/m` in § 2.10) — **Evidence:** `public/pages/savetax.php:124-373`, `public/pages/js/savetax.js:4-45, 155-198`; the five screens above; step label read "1 of 4" and became "4 of 5" after Yes to spouse (`savetax.js:37-45`).

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Route | `routes/web.php:707-725` (Save Tax), `:632-650` (Pension Check) | `GET /savetax`, `/savetax/plan`, `/pensioncheck`, `/pensioncheck/plan` include a PHP page; guests only (`redirect.authed`), cached five minutes |
| 2 | Page | `public/pages/savetax.php:3-16` | reads the income band labels from `FunnelIncomeBand::pageLabels()` with a hardcoded fallback if it throws |
| 3 | Page | `public/pages/pensioncheck.php:161-205` | the income band labels are hardcoded in the HTML (MB-38) |
| 4 | Script | `savetax.js:12-19`, `pensioncheck.js:12-19` | an allowlisted `utm_source` is stashed in `sessionStorage['fynla.signup_source']` (linkedin, facebook, instagram, tiktok, x, youtube) |
| 5 | Script | `savetax.js:24-35`, `pensioncheck.js:24-36` | on the last screen: answers saved to `localStorage['savetax_answers']` or `['pensioncheck_answers']` and passed as query parameters to the plan page with `from=<campaign>` |
| 6 | Phone | `RedirectPhoneToMobile.php:43-45, 64-81` | a phone visitor on `/savetax` or `/pensioncheck` is redirected to `/m?to=/savetax` (query preserved); `mobile-host.blade.php:29-33` frames the validated path inside the `/m` host |

**Form fields (Save Tax)**

| Label shown | Field | Input type | Values | Stored as | Surfaces |
|---|---|---|---|---|---|
| What is your employment status? | `employment` | single tap | `not-employed`, `part-time`, `full-time`, `self-employed`, `retired` | `funnel_answers.employment` | web, `/m` (framed) |
| What is your annual income? | `income` | single tap | `upto_50270`, `50271_100000`, `100001_125140`, `over_125140` (labels from tax config) | `funnel_answers.income` + `income_context` | web, `/m` |
| Do you have a spouse? | `spouse` | single tap | `yes`, `no` | `funnel_answers.spouse` | web, `/m` |
| What is your spouse's annual income? | `spouseIncome` | single tap, only after Yes | `zero` plus the four bands | `funnel_answers.spouseIncome` + `spouse_income_context` | web, `/m` |
| Which of these do you have? | `assets` | multi-select, may be empty | `bank`, `savings`, `pension`, `property`, `isa`, `investments` | `funnel_answers.assets` | web, `/m` |

**Form fields (Pension Check)** (`public/pages/pensioncheck.php:107-386`, `pensioncheck.js:4, 173-200`)

| Label shown | Field | Values | Notes |
|---|---|---|---|
| What is your employment status? | `employment` | as Save Tax | |
| What is your annual income? | `income` | as Save Tax, labels hardcoded | MB-38 |
| How old are you? | `age` | `under_30`, `30s`, `40s`, `50s`, `60_plus` | |
| Which types of pension do you have? | `pensions` | `workplace`, `personal_sipp`, `final_salary`, `none` (`none` is exclusive) | multi-select with Continue |
| What is the total value of your pension pot? | `pot` | `none`, `under_25k`, `25k_100k`, `100k_250k`, `over_250k` | |
| Do you have a spouse? | `spouse` | `yes`, `no` | no spouse income question |

**Other pages on these paths.** `/savetax/v2`, `/savetax/plan/v2`, `/savetax/plan/v3`, `/savetax/plan/v4` are design mock-ups served without the guest-only guard (`routes/web.php:660-702`, already MB-10). The Vue pages `/quickstart`, `/biggerpension`, `/paymortgage`, `/managedebt` and `/wealth` (`router/index.js:252-286`, `CampaignPage.vue:208-286`) are live SPA marketing pages whose only calls to action go to `/register?from=fyn` (`CampaignPage.vue:39, 193`; `QuickStartPage.vue:59, 189`); `fyn` is in neither `campaign_map` nor `journey_map` (`config/onboarding.php:57-86`), so they start the ordinary path-choice onboarding, not a campaign. The Vue `/savetax` page (`SaveTaxCampaignPage.vue`) is the server-shadowed copy already recorded in MB-09. The phone redirect allowlist names `biggerpension`, `paymortgage`, `managedebt` and `wealth` as campaign prefixes (`RedirectPhoneToMobile.php:44`) although no funnel or campaign exists for them (MB-39).

**Fyn touchpoints:** none on the public pages. The v3 mock-up's script (`savetax-plan.js:198-268`) renders a fake chat whose every interaction redirects to `/register?from=savetax`; the live plan page has no chat.

**Background machinery:** none.

**Tests**

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| `tests/Feature/PublicPages/PensioncheckRoutesTest.php` | the two Pension Check routes render and bounce authenticated users | yes | 3 passed (in the 658) |
| `tests/Feature/Marketing/SaveTaxClaimClarityTest.php` | the plan page copy and allowance states | yes | 10 passed |
| `tests/E2E/public/savetax-clarity.spec.js` | Playwright: widths, stale storage vs fresh URL answers, existing-account link | no (targets a deployed host) | not run |

### 2.2 The estimate pages

**In plain English.** The results page turns the banded answers into a headline figure. Save Tax shows "an average estimated saving of up to £X each year" and a two-column list of allowances, each marked available, used automatically or not applicable, with a reason. Pension Check shows a projected pension pot at State Pension age, the assumptions behind it, and illustrative testimonials. Both pages assume the top of the income band the person chose, say the figure is not advice, and put the sign-up form beside the headline.

**Status:** Working (Save Tax, this run: headline £12,527, allowances total £186,070, tax year 2026/27 for band `50271_100000`, spouse `zero`, assets savings/pension/isa/investments); Unverified (Pension Check page rendering; the service is unit-tested) — **Evidence:** `window.SAVETAX_ESTIMATE` read from the page this run; `SaveTaxEstimateService.php:46-175`; `PensionEstimateService.php:119-151`; `tests/Unit/Services/Marketing/SaveTaxEstimateServiceTest.php` 23 passed, `PensionEstimateServiceTest.php` passed this run.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Page | `savetax-plan.php:9-37` | reads `income`, `spouse`, `spouseIncome`, `assets` from the query; a direct visit with no income falls back to a representative persona (`50271_100000`, no spouse, savings + pension + isa) |
| 2 | Service | `SaveTaxEstimateService::estimate()` `:46-175` | assumed income = top of the band (`FunnelIncomeBand::assumedIncome()`, `over_125140` uses `config('onboarding.savetax_over_band_assumed_income')` = 150000, `config/onboarding.php:38`); marginal rate, then one saving line per lever: pension or the 60% trap (only if no pension ticked), ISA (10% of income at the marginal rate), Personal Savings Allowance, dividend allowance, Capital Gains Tax allowance, and four spouse levers when the spouse earns nothing |
| 3 | Service | `:184-343` | the allowance list: Personal Allowance (tapered label above the threshold), ISA, Pension Annual Allowance (non-earner note), PSA, dividend, CGT, then Marriage Allowance and the spouse's own set when married; `total` sums only the `available` items |
| 4 | Page | `savetax-plan.php:203-205` | the whole estimate is injected as `window.SAVETAX_ESTIMATE`; `savetax-plan-v4.js` renders it (the live page loads the v4 script, `savetax-plan.php:205`) |
| 5 | Script | `savetax-plan-v4.js:41-45, 67-76` | a "Could save £X/yr" callout is attached only to allowance keys in `SAVING_FOR` (`pension_aa`, `psa`, `dividend`, `cgt`, `marriage_allowance`, `spouse_pa`, `personal_allowance`); the `isa`, `spouse_psa` and `spouse_starting_rate` saving lines are in the headline total but never shown as a line (MB-40) |
| 6 | Page | `pensioncheck-plan.php:8-34` | reads the six answers; `PensionEstimateService::estimate()` compounds the pot midpoint plus 8% of the income midpoint monthly at 2.5% real to State Pension age (`StatePensionAgeResolver::forCurrentAge`), or shows the current pot for a retired person |
| 7 | Script | `pensioncheck-plan.js:81-138, 221-298` | hero figures, four projection rows, and illustrative testimonials chosen by the answers (the counts and quotes are sample content, `:25-26`) |

The public allowances endpoint `GET /api/public/tax-allowances` (`routes/api.php:244-245`, `TaxAllowancesController.php:25-98`) is called only by the v2 and v3 mock-up scripts and the shadowed Vue page; the live plan page does not call it (MB-41).

**Tests**

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| `tests/Unit/Services/Marketing/SaveTaxEstimateServiceTest.php` | bands, trap, spouse levers, allowance states | yes | 23 passed |
| `tests/Unit/Services/Marketing/PensionEstimateServiceTest.php` | projection, retired, non-contributor, relief note | yes | passed (in the 658) |
| `tests/Unit/Services/Onboarding/FunnelIncomeBandTest.php` | band boundaries from tax config, labels, contexts | yes | 8 passed |

### 2.3 Registration from the plan page and the hand-off

**In plain English.** The four-field form on the results page creates the account directly. The server sends the six-digit code email and returns an encrypted, short-lived token. The page then opens the web app's register screen with that token; the app shows only the code entry, never a second form, and after the code is accepted it opens the dashboard with Fyn ready. If the email belongs to a deleted account that can still be restored, the same token carries the person into the restore flow instead.

**Status:** Working (web, this run: user 90 created from the compact form; the hand-off URL was cleaned to `/register?from=savetax`; the verification modal showed the masked email; the dashboard opened with Fyn) — **Evidence:** `savetax-plan-v4.js:211-293`; `AuthController.php:67-166, 189-199`; `RegistrationHandoffService.php:26-75`; `Register.vue:257-262, 310-345, 495-515`; screenshot `web-register-01-handoff-verify.png`; `pending_registrations` row 9 read by tinker this run with the stamped `funnel_answers` and `signup_source = linkedin`.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Script | `savetax-plan-v4.js:130-165` (`pensioncheck-plan.js:306-340`) | builds `funnel_answers` from the URL, falling back to localStorage; always sets `campaign` |
| 2 | Script | `:232-256` | primes the CSRF cookie, then `POST /api/auth/register` with first name, surname, email, password, `funnel_answers`, and `signup_source` if stashed |
| 3 | Request | `RegisterRequest.php:58-99, 128-143` | unknown campaign names and unknown band values are stripped, not rejected; arrays are filtered to the allowlist; the pensioncheck keys `age`, `pensions`, `pot` are allowed |
| 4 | Controller | `AuthController.php:70-71, 167-187` | for `savetax` only, the income and spouse income bands are stamped with their `FunnelIncomeBand::context()` (bounds, labels, tax year) |
| 5 | Controller | `:73-85` | a soft-deleted restorable account returns `account_deleted_restorable` plus a restoration hand-off token |
| 6 | Controller | `:110-127` | `PendingRegistration::createOrUpdate` keeps earlier `funnel_answers` and `signup_source` if the new call has none (`PendingRegistration.php:66-95`) |
| 7 | Controller | `:155-157` | the verification hand-off token is issued for the pending row and the campaign |
| 8 | Service | `RegistrationHandoffService.php:26-43, 163-170` | token = encrypted JSON: kind, source (must be a `campaign_map` key), subject id, a fingerprint of the password hash plus the verification code, expiry in 15 minutes |
| 9 | Script | `savetax-plan-v4.js:274-287` | redirects to `/register?from=<campaign>&handoff=<token>` |
| 10 | SPA | `Register.vue:257-262` | strips `handoff` from the address bar immediately; `:310-345` posts it to `POST /api/auth/registration-handoff/resolve` (allowed in preview mode, `PreviewWriteInterceptor.php:61`) and opens the verification modal (masked email) or the restore modal |
| 11 | SPA | `:497-515` | after the code: `Dashboard` with `openFyn=journey&from=<source>`; a restored account goes the same way (`:563-566`) |
| 12 | Controller | `AuthController.php:606-628` | verification creates the user with `funnel_answers` and `signup_source`, then `FunnelAnswersMapper::mapToProfile` |
| 13 | Service | `FunnelAnswersMapper.php:28-71` | never overwrites: `employment_status` from the funnel value, `marital_status` married or single from the spouse answer, and for a spouse with a stated band `household_calculation_mode` (`dual_earner` or `single_earner_couple`) plus `marriage_allowance_eligible` |

This run, user 90 after verification: `employment_status = full_time`, `marital_status = married`, `household_calculation_mode = single_earner_couple`, `marriage_allowance_eligible = true` (tinker).

**Tests**

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| `tests/Feature/Auth/CampaignRegistrationHandoffTest.php` | token issue, resolve, expiry, fingerprint, restoration | yes | 18 passed |
| `tests/Feature/Auth/FunnelAnswersCaptureTest.php` | the request boundary and the mapper | yes | 7 passed |
| `tests/Feature/Auth/PensioncheckRegistrationPayloadTest.php` | the pensioncheck keys survive validation | yes | 3 passed |
| `tests/E2E/journeys/user-reported-campaign-regressions.spec.js` | ten Playwright journeys: fresh registration, phone hand-off into `/m/app`, existing-account response, spouse skip, dependant date, restoration, ISA and £10,000 expenditure before the strategy | no (targets csjones) | not run |

### 2.4 Choosing the campaign when Fyn starts

**In plain English.** When the dashboard opens Fyn for a new arrival, the server decides which walk to run. It prefers a campaign named in the link, then a walk the person paused, then the campaign stored in their funnel answers. A completed user is turned away unless the campaign allows re-entry (Pension Check does, Save Tax does not) or they are already mid-campaign. The first Fyn message recaps the funnel answers and asks for income.

**Status:** Working (web this run: `POST /api/ai-chat/onboarding/start` with `from=savetax` set `onboarding_fyn_path = campaign`, `selection = savetax`, `step = base_work`, and the first turn was the funnel recap) — **Evidence:** `AiChatController.php:618-892`; `resources/js/store/modules/aiChat.js:1372-1382`; `aiChatService.js:231-245`; `Dashboard.vue:1377-1400`; tinker readout of user 90 after the first turn; `tests/Feature/AI/OnboardingStartCampaignMapTest.php`, `CampaignReentryStartTest.php`, `CampaignReentryDispatchTest.php`, `CampaignReentryExitTest.php`, `EntrySourceCampaignMapTest.php` (27 tests) passed this run.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Config | `config/onboarding.php:79-86` | `campaign_map`: `savetax` (entry `base_work`, no re-entry) and `pensioncheck` (entry `base_work`, re-entry allowed at `campaign2_existing_recap`) |
| 2 | Client | web `Dashboard.vue:1387-1400`, `/m` `views/Dashboard.vue:927-939, 984-990`, `mixins/onboardingChat.js:165-176` | the `from` query value is forwarded in the start request body |
| 3 | Controller | `AiChatController.php:635-640` | a re-entry campaign is one named in `from` with `reentry` true |
| 4 | Controller | `:647-664` | a paused campaign (`onboarding_fyn_context.paused_at_step` with `path = campaign`) also counts, as does an in-flight `active_campaign` with a step (`:670-675`) |
| 5 | Controller | `:680-685` | completed and no re-entry: `409 already_completed`; flag off: 503; preview user: 403 |
| 6 | Controller | `:698-728` | a step already set: emit a `resume` event pointing at the latest onboarding conversation |
| 7 | Controller | `:750-774` | otherwise match in order: `from` in `campaign_map`, `from` in `journey_map`, the paused campaign, then `funnel_answers.campaign` (rows without the key default to `savetax`) |
| 8 | Controller | `:776-814` | stamp path, selection and step (entry, `reentry_entry` for a completed re-entrant, or the paused step); stamp `active_campaign` on re-entry |
| 9 | Controller | `:817-838` | reuse the paused conversation, else create one with `metadata.source = fyn_onboarding` (plus `metadata.campaign` on re-entry) |
| 10 | Director | `OnboardingChatDirector::emitFirstTurn` (02a) at `base_work` | `OnboardingStateMachine::buildWorkPrompt()` `:1552-1580` emits the funnel recap once per conversation (`stateTurnAlreadyDelivered`), pension-flavoured for pensioncheck |
| 11 | Every later turn | `ConversationModeResolver` (02a § 2.2) | `(completed false or active_campaign set) and step set` routes to the director; the `/m` and iOS clients mirror the same predicate to decide whether to show onboarding affordances (`onboardingChat.js:53-56`, `SettingsModel.swift:86-90`) |

The recap this run: "Working full-time", "Earning £50,271–£100,000", "You have a spouse" (no income phrase because the spouse band was `zero`, `buildFunnelRecapPrompt` `:1426-1437`), "You have savings, a pension, an ISA and investments", "about 6 minutes" (3 + 3 extra assets, `:1456-1458`).

**CRUD**

| Operation | Method and endpoint | Who may call it | Writes | Returns |
|---|---|---|---|---|
| Start | `POST /api/ai-chat/onboarding/start` (`from` optional) | any signed-in user with AI chat consent; preview users refused | `users.onboarding_fyn_*`, `active_campaign`, `onboarding_started_at`; an `ai_conversations` row | SSE: `conversation_created` then the first turn; or `resume`; or 409/503/403 JSON |
| Status | `GET /api/ai-chat/onboarding/status` | signed-in | nothing | the director's status payload (02a) |
| Message | `POST /api/ai-chat/conversations/{id}/messages` | signed-in, consent | every campaign capture below | SSE turns |

### 2.5 The Save Tax section walk

**In plain English.** After the income question, Fyn walks the person through only the sections their funnel answers justify: ISAs and savings if they ticked them, investments if they ticked them, pensions always (date of birth first, then the workplace scheme if they are employed, then personal contributions), the spouse's position if they are married, then monthly spending. Every section ends with the same check: Fyn saves what it heard, opens the matching page, and asks whether it looks right. Saying no opens an edit turn. Saying yes triggers a short piece of advice for that section, then the next section. A consent question sits between the income section and the first asset section.

**Status:** Working on web this run (user 90: married, spouse with no income, savings + pension + ISA + investments; every state below was driven), with the breaks recorded as MB-37, MB-46, MB-47, MB-48, MB-49, MB-54 — **Evidence:** `OnboardingStateMachine.php:189-262, 446-722, 1873-2000`; corpus blocks `campaign_intro` to `campaign_spouse_invite_details` in `fyn-onboarding.v1.md`; `ai_messages` 568-620 for conversation 190 (user 90); `tests/Unit/Services/Onboarding/CampaignSectionFlowTest.php` (22), `CampaignStateMachineBranchTest.php` (27), `CampaignVerifyFlowTest.php` (19), `CampaignBubbleCaptureTest.php` (3) passed this run.

**How the order is decided**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Order | `OnboardingStateMachine::CAMPAIGN_SECTION_ORDERS['savetax']` `:189` | `income`, `savings`, `investments`, `pensions`, `spouse`, `expenditure` |
| 2 | Section map | `campaignSections('savetax')` `:210-217` | entry state and whole-section skip per section: savings skipped unless bank, savings or ISA ticked (`skipSectionIfNoCash` `:1675-1678`); investments skipped unless investments ticked or a Stocks & Shares ISA was captured (`:1730-1742`); spouse skipped unless married |
| 3 | Next section | `nextCampaignSection()` `:281-306` | after a section's advice, the first non-skipped section's entry state, with per-state `skip_if` applied transitively; synthesis when exhausted |
| 4 | Verify | `enterCampaignVerify()` `:1025-1042` | stamps `onboarding_fyn_context.verify_section`, enters `campaign_verify_announce`; `campaignVerifyConfig('savetax')` `:239-254` gives the route per section (`/income`, `/savings`, `/investment`, `/retirement`, `/income` for spouse, `/expenditure`) |
| 5 | After yes | `nextFromVerifyNavigate()` `:1073` → `campaignSectionAdvice()` `:1050-1066` | the section's advice state, then `nextCampaignSection` |

**States driven this run, in order**

| State | Turn | What Fyn did this run | Wrote |
|---|---|---|---|
| `base_work` | grouped_extract | funnel recap then the income question; "£75,000 a year, product manager at Acme Ltd" → "Got it — £75,000 a year, noted" | `users.annual_employment_income = 75000` (audit `capture_work_details`) |
| `base_employment_more` | bubbles | "No, that's everything" | nothing |
| `campaign_verify_announce` → `navigate` (income) | bubbles + `navigation` | opened `/valuable-info?section=income` showing £75,000 and the tax breakdown; "Yes, that's right" | nothing |
| `campaign_advice_income` | advice | nothing voiced at £75,000 with no records (auto-advanced) | nothing |
| `campaign_intro` | bubbles | "…I'd like to ask about your bank and savings accounts, pensions, ISAs and investments, including your spouse's where it makes sense, is that okay?" (asset groups from the funnel, `buildCampaignIntroPrompt()` `:1873-1926`); Okay | nothing |
| `campaign_isa_holdings` | delegated | "One Cash ISA with Nationwide, £15,000, £4,000 this year" → "Recorded — one Cash ISA" plus a saved-record card | `savings_accounts` 237 (`cash_isa`, 15000) |
| `campaign_bank_accounts` | delegated | prompt named only savings accounts (`buildCampaignBankAccountsPrompt()` `:1762-1777`); Marcus £30,000 at 4.1% → recorded | `savings_accounts` 238 |
| verify (savings) | bubbles + navigation | opened `/savings`: no accounts shown (MB-37); "Yes" | nothing |
| `campaign_advice_savings` | advice | "Wrap £16,000 of cash savings inside an ISA before April 5… £1,230.00 of annual interest… saving around £262 a year… Start with Marcus" + "I've added this to your actions list" | nothing (the composed plan is recomputed, not stored) |
| `campaign_investment_accounts` | delegated | Vanguard General Investment Account £25,000, cost £20,000, £600 dividends → recorded | `investment_accounts` 123 |
| verify (investments) | navigation | opened `/net-worth/investments` showing Vanguard £25,000; "No, change something" | nothing |
| `campaign_verify_edit` | delegated (update-only tools) | first phrasing failed the honesty gate (MB-49); "Change the current value from £25,000 to £27,000" → `update_record` landed → read-back "current value now £27,000" → the navigate prompt re-emitted; page still showed £25,000 (MB-47); "Yes" | `investment_accounts.current_value = 27000` |
| `campaign_advice_investments` | advice | nothing voiced | |
| `campaign_dob` | grouped_extract | pension-framed wording (pension ticked); "19/02/82" → the short-year confirm "Your date of birth is 19th February 1982 — is that correct?" (`maybeConfirmShortDob()` `OnboardingChatDirector.php:3501-3557`); Yes | `users.date_of_birth` |
| `campaign_occupational_scheme` | delegated, `capture_focus = occupational` | 5% employee, 3% employer, salary sacrifice, £40,000 pot, Aviva → the model refused (`ai_messages` 610, no tool call); the deterministic backstop wrote 5%/3% with no pot and no provider (MB-54, cause corrected 2026-09-14); the sacrifice flag landed on the next turn's blocked-attempt retry | `dc_pensions` 71 |
| `campaign_pension_contribs` | delegated, `record_context = pensions` | "No" → "Recorded — no personal pension or SIPP. Recorded — salary sacrifice confirmed." — the blocked `capture_salary_sacrifice` from the previous turn was retried here (`retryPreviousBlockedAttempt()` `:6067`) and stamped `users.employment_income_basis = gross` | `dc_pensions.salary_sacrifice`, `users.employment_income_basis` |
| verify (pensions) | navigation | opened `/net-worth/retirement` listing the workplace pension at 5%; "Yes" | |
| `campaign_advice_pensions` | advice | nothing voiced | |
| `campaign_spouse_work` | bubbles | skipped: `household_calculation_mode` was already `single_earner_couple` from the funnel (`skipSpouseWorkIfModeKnown()` `:1979-1982`) | |
| `campaign_spouse_non_working_assets` | grouped_extract `capture_spouse_non_working_assets` | prompt with the hardcoded "£40,000" (MB-46); "Cash ISA with £5,000, nothing else" → captured | `tax_strategy_household_inputs` row 1 (`spouse_existing_isa_balance = 5000`, others 0) |
| verify (spouse) | navigation | opened `/valuable-info?section=income`: nothing about the spouse (MB-48); "Yes" | |
| `campaign_advice_spouse` | advice | "You can definitely save money with your spouse's allowances — around £1,012 a year. We've added this to your actions list…" (`buildSpouseAdvice()` `:1367-1405`) | |
| `base_expenditure` | free text | "About £3,200 a month" → "Recorded monthly spending of £3,200" | `users.monthly_expenditure`, `expenditure_profiles.total_monthly_expenditure = 3200` |
| verify (expenditure) | navigation | opened `/valuable-info?section=expenditure` showing £0 (MB-47, MB-27); "Yes" | |
| `campaign_synthesis` | advice | five bullets in composed-plan order, "Together these are worth roughly £1,274 a year", the adviser signpost (§ 2.6) | |
| `campaign_spouse_invite` | bubbles | "Shall I send them an invitation?" (married, no live link, none pending: `skipSpouseInviteIfLinked()` `:2158-2168`); "Not now" | `onboarding_fyn_context.spouse_invite = declined` |
| `campaign_terminal` | terminal | "No problem. You can invite them any time from Family in Settings." then the app note then "We've created your personal tax strategy, Mapper." with "Take me to my tax strategy" | completion columns (§ 2.7) |

Not driven this run: `campaign_spouse_household` (dual earner), `capture_salary_sacrifice` on its own turn, the "Yes, invite them" branch (sends a real email), the "No thanks" exit at `campaign_intro`, a walk with `bank` but not `savings`, a walk without a pension. Each of those is code-traced only (Unverified).

**Fyn touchpoints (write path).** Delegated states offer the tools in `OnboardingPromptBuilder::toolsForFocus('savetax')` `:133-144` plus `update_profile` and `update_record`; grouped-extract states offer one extraction tool. The campaign tool schemas live in `fyn-memory/procedural/tool_schema/campaign/` (`.md` for Anthropic, `.xai.md` for xAI with nullable fields) and are loaded by `AiToolDefinitions::ORDER['campaign']` `:115-124` for both the general catalogue and the onboarding extraction list (`:405-417`); `XaiToolDefinitions.php:111-119` mirrors the list. Handlers:

| Tool | Handler | Writes | Evidence this run |
|---|---|---|---|
| `capture_salary_sacrifice` | `CoordinatingAgent::handleCaptureSalarySacrifice()` `:5456-5542` | `dc_pensions.salary_sacrifice`, `employer_ni_rebate_pct`; `users.employment_income_basis` once, only when sacrifice is true and the column is null | audit 90-91; `basis = gross` |
| `capture_spouse_work_status` | `:5544-5566` (fired synchronously by the bubble, `dispatchBubbleCapture()` `OnboardingChatDirector.php:2077-2096`) | `users.household_calculation_mode`, `marriage_allowance_eligible` | skipped this run (mode pre-set by the funnel) |
| `capture_spouse_household_data` | `:5568-5640` | `tax_strategy_household_inputs` dual-earner columns; `spouse_psa_band` recomputed from income plus dividends | not driven; `CaptureSpouseHouseholdDataTest` passed |
| `capture_spouse_non_working_assets` | `:5642-5688` | `tax_strategy_household_inputs` `spouse_existing_*` | audit 93-94; row 1 |
| `capture_pension_history` | `:5690-5760` | `pension_input_history` rows via `PensionStore::captureInputHistory`; an ambiguous single figure is refused with a clarifying question; "in total" is split evenly | driven on `/m` (§ 2.8) |
| `capture_charitable_giving` | `:5970-5996` | `users.charitable_donations` (monthly) | no state reaches it (MB-43) |
| `create_savings_account`, `create_investment_account`, `create_pension`, `update_record` | module handlers (06, 07, 08) | module rows | audit 78-79, 82-83, 88-89 |

**Background machinery**

| Kind | Name | Trigger | Effect | Evidence |
|---|---|---|---|---|
| Observer | `UserOnboardingStepObserver` | any change to `users.onboarding_fyn_step` | clears the `/m` dashboard cache | `app/Observers/UserOnboardingStepObserver.php:22-29` |
| Observer | risk recalculation | investment update | "Recalculating risk profile for user 90 {trigger: investment_updated}" | `storage/logs/laravel.log` 15:17:22 this run |
| Activity feed | `ActivityFeedService::STEP_LABELS` | each campaign state completed | feed lines such as "Told us about your ISAs" | `ActivityFeedService.php:43-50` |
| Ranking | `NextActionsService::applyCampaignAffinity()` | dashboard actions for a campaign user | tax module first for Save Tax, retirement first for Pension Check | `NextActionsService.php:36-38, 266-278`; `CampaignAffinityTest` 4 passed |

### 2.6 Advice turns and the synthesis

**In plain English.** After each confirmed section Fyn may say one or two things worth doing, taken from the same ranked plan the Tax Strategy page shows, and tells the person it has been added to their actions. At the end it reads the whole plan back in order with a total, and reminds them that regulated advice comes from an adviser. Pension Check does the same against the retirement plan. If the engine has nothing to say, the turn passes silently.

**Status:** Working (savings advice, spouse advice and synthesis voiced on web this run; retirement-goals advice and synthesis voiced on `/m`) — **Evidence:** `OnboardingChatDirector.php:1106-1510`; `CampaignSynthesisTurnTest` (4) and `PensioncheckSynthesisTurnTest` (8) passed; `PensioncheckSectionAdviceTest` (7), `PensioncheckCrossCampaignAdviceTest` (7) passed.

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Director | `emitAdviceTurn()` `:1106-1177` | builds the text, saves it with `advice_section`, advances; a self-transition or a chain deeper than `MAX_ADVICE_CHAIN` forces completion (the PR #504 guard) |
| 2 | Section map | `SECTION_STRATEGY_TYPES` `:1215-1223` (savetax), `PENSIONCHECK_SECTION_STRATEGY_TYPES` `:1226-1230` | which composed-plan item types may be voiced per section; at most two; `giving` is never voiced (MB-43); `state_pension` is always empty |
| 3 | Plan | `ComposedTaxPlanService::forUser()` or `ComposedModulePlanService::forSource(RetirementStrategySource)` | recomputed on the fresh records each time (`:1412-1416`) |
| 4 | Voicing | `:1265-1290`, `:1340-1364` | mechanical items stated plainly, judgement items prefixed "You may want to consider:", then "I've added this to your actions list to come back to later." |
| 5 | Spouse | `buildSpouseAdvice()` `:1367-1405` | one line with the combined saving, or the spouse's Personal Allowance plus ISA allowance from `TaxConfigService` when the engine has no figure |
| 6 | Synthesis | `buildSynthesisAdvice()` `:1408-1489` | bullets in composer order with "saves around £X a year" unless the title already carries the figure; "Together these are worth roughly £X a year" when the combined saving is positive; an honest fallback line when the plan is empty |

This run's synthesis (web, user 90): "Top up your spouse's pension by £2,880 — instant £720 of free money", "Wrap £16,000 of cash savings inside an ISA before April 5 — saves around £262 a year", "Gift £30,000 of savings to your spouse for up to £18,570 of interest tax-free every year — saves around £292 a year", "Consider sharing savings equally to use both partners' tax positions — saves around £246 a year", "Hold non-ISA investments in your spouse's name"; total £1,274. The Tax Strategy page headline afterwards read "Mapper, save up to £1,274 this year" and its `composed_plan.combined_annual_saving` was 1274.4 (API read this run), so chat and page agreed.

### 2.7 Completion and the terminal turn

**In plain English.** When the walk is finished Fyn says whether the spouse invitation was sent, mentions the app, then congratulates the person with a single button to their plan. Tapping it opens the Tax Strategy page (Save Tax) or the Retirement page (Pension Check). Behind the scenes onboarding is marked complete and every campaign marker is cleared; a returning Pension Check user keeps their original completion date.

**Status:** Working (web: `campaign_terminal` → `/tax-strategy`; `/m`: `campaign2_terminal` → `/m/app/retirement`, both this run) — **Evidence:** `OnboardingChatDirector::emitTerminalNavigationTurn()` `:5664-5785`; `terminalNavigationBubble()` `:5539-5548`; tinker after each: user 90 `onboarding_completed = true`, `onboarding_fyn_step/path/selection/context = null`, `active_campaign = null`; `CampaignReentryExitTest` (3) passed.

| Step | File | What happens |
|---|---|---|
| 1 | `:5679` | any questions deferred mid-walk are raised first (02a § 2.6) |
| 2 | `:5688-5699` | the invitation outcome line, when one was offered: sent, created, already linked, declined, collision, failed (`takeSpouseInviteOutcomeText()` `:2141-2172`) |
| 3 | `:5701-5717` | "By the way — the Fynla experience is even better in the app…" |
| 4 | `:5725-5744` | the celebration from the state's `prompt_text` plus one route bubble: "Take me to my tax strategy" or "Take me to my retirement plan" |
| 5 | `:5747-5753` | `onboarding_complete` event with `nextRoute`; `/m` mirrors `onboarding_completed = true` and `active_campaign = null` into its store (`onboardingChat.js:411-421`) |
| 6 | `:5758-5776` | columns cleared; completion stamped only if not already completed; `recordProgress` once |

### 2.8 The Pension Check walk and re-entry

**In plain English.** Pension Check asks about income, then everything pension-shaped: date of birth, the workplace scheme (employed people only), the value of any pot Fyn does not yet have a figure for, personal contributions, final-salary pensions, the last three years of contributions (higher-rate taxpayers only), whether any pension has been drawn (over-55s only), the State Pension forecast, the retirement target, the spouse's pensions (married people only), and monthly spending. Someone who has already finished onboarding can come back into this walk: Fyn recaps what it holds, asks whether anything has changed, and then asks only the sections it does not have.

**Status:** Working on `/m` this run (user 91: self-employed, in their 50s, personal and final-salary pensions, £100,000 to £250,000 pot, no spouse; every state below was driven, then re-entry to the recap and one gap-walk step), with MB-50, MB-51, MB-52, MB-53 raised — **Evidence:** `OnboardingStateMachine.php:191, 224-231, 268-278, 1685-1758, 2000-2120, 2220-2390, 2542-2615`; corpus blocks `campaign2_*`; `ai_messages` 640-700 for user 91; `PensioncheckStatesTest` (63), `PensioncheckSectionsTest` (26), `PensioncheckRouteFixesTest` (11), `PensioncheckCaptureFocusTest` (2), `CampaignReentryStartTest` (6), `CampaignReentryDispatchTest` (7), `PensioncheckRegistrationPayloadTest` (3) passed this run.

**States driven this run, in order** (`CAMPAIGN_SECTION_ORDERS['pensioncheck']` = `income`, `pensions`, `state_pension`, `retirement_goals`, `spouse`, `expenditure`)

| State | What Fyn did this run | Wrote |
|---|---|---|
| `base_work` | pension-flavoured recap ("about 4 minutes", pension types listed from `funnel_answers.pensions`, `buildPensioncheckFunnelRecapPrompt()` `:2542-2608`); "£62,000 from my consultancy" | `users.annual_self_employment_income = 62000` |
| `base_employment_more` → verify (income) | `/m/app/income?section=income` showed "Self-employment £62,000" with the Continue and Edit pills; Continue | |
| `campaign_advice_income` | silent for Pension Check (`buildSectionAdvice()` `:1252-1258`) | |
| `campaign_dob` | neutral wording (MB-51c); "3 March 1968" (age 58) | `users.date_of_birth` |
| `campaign_occupational_scheme` | skipped (self-employed, `skipIfOccupationalScheme()` `:2019-2032`) | |
| `campaign2_pension_pots` | skipped (no DC pension without a value, `skipIfNoPensionPotToFill()` `:2063-2066`) | |
| `campaign_pension_contribs` | "Beyond the workplace pension we covered…" (MB-51b); Vanguard SIPP £180,000, £8,000 a year → "Recorded — Vanguard SIPP £180,000"; the contribution landed two turns later ("Recorded — £8,000 yearly into Vanguard SIPP", the blocked-attempt retry) | `dc_pensions` 72 (`sipp`, 180000; `monthly_contribution_amount = 666.67` after the retry) |
| `campaign2_pension_db` | NHS pension £9,500 a year → recorded | `db_pensions` 32 (`accrued_annual_pension = 9500`) |
| `campaign_pension_history` | asked (£62,000 is above the higher-rate threshold, `skipIfPensionHistoryNotApplicable()` `:2043-2057`); "About £24,000 in total across the three years" → split evenly | `pension_input_history`: 2023/24, 2024/25, 2025/26 at £8,000 each |
| `campaign2_flexible_access` | asked (age 58); "No" → "Recorded — no pension withdrawals" | nothing (no flag write on No) |
| verify (pensions) | `/m/app/retirement?section=pensions` listed both pensions ("2 of 2 pensions used", "Upgrade" — the free-tier cap), the £9,500 projection; Continue | |
| `campaign_advice_pensions` | silent (no matching retirement items) | |
| `campaign2_state_pension` | "£11,500 a year, 32 qualifying years" → "Updated Retirement" | `state_pensions` 70 (`forecast 11500`, `ni_years_completed 32`, `state_pension_age 67`) |
| verify (state pension) | announce read "I've saved your details… your details page" (MB-51a); the retirement screen showed "State Pension £11,500 a year"; Continue | |
| `campaign2_advice_state_pension` | always silent (`:1229`) | |
| `campaign2_retirement_goals` | "retire at 66 with about £32,000" → recorded | `retirement_profiles` (`target_retirement_age 66`, `target_retirement_income 32000`) |
| verify (goals) → `campaign2_advice_retirement_goals` | the screen showed the target; then two judgement items ("Approaching Retirement — Review Decumulation Strategy", "Consider Adjusting Retirement Age… 69 instead of 66") rendered as one run-on paragraph on `/m` (MB-52) | |
| `campaign_spouse_work` | skipped (single) | |
| `base_expenditure` | "£2,400 a month" → recorded; the `/m` expenditure screen showed £3,067 (MB-50); Continue | `users.monthly_expenditure = 2400` |
| `campaign_synthesis` | "Here's your pension picture…": Approaching Retirement, Increase Pension Contributions, Consider Adjusting Retirement Age, Care Costs Not Included; no total (retirement items carry no saving); the adviser signpost | |
| `campaign_spouse_invite` | skipped (single) | |
| `campaign2_terminal` | app note, "We've built your pension picture, Mobile.", "Take me to my retirement plan" → `/m/app/retirement` showing £24,155 a year projected against the £32,000 target | completion columns |

**Re-entry, driven this run.** Navigating to `/m/app/dashboard?from=pensioncheck` as the completed user 91: the dashboard opened Fyn (`views/Dashboard.vue:984-990`), `startOnboarding` matched the re-entry campaign (`:635-640`), stamped `onboarding_fyn_step = campaign2_existing_recap` and `active_campaign = pensioncheck` (tinker), created a second conversation with `metadata.campaign`, and Fyn said "Welcome back, Mobile. Let's take a proper look at your pension. Here's what I already have from you:" with three bullets (income, Vanguard SIPP £180,000 pot, NHS Pension Scheme £9,500 a year accrued — `buildExistingRecapPrompt()` `:2220-2300`) and "Is that all still right?". A level-up dialog ("Strategist") had to be dismissed first. "Yes, that's right" → `firstCampaignSection()` `:2388-2408` skipped income (known) and landed on `campaign_pension_contribs`, re-asking the contribution already on file (MB-53). The walk was left there: user 91 is mid re-entry (`active_campaign = pensioncheck`).

Not driven: "Something's changed" at the recap (`campaign_verify_edit` with `verify_section = recap`, `:2330-2350`); a married Pension Check walk (`campaign2_spouse_pensions`); an employed walk (`campaign2_pension_pots` loop); the under-55 skip of flexible access; the Save Tax re-entry (refused by config).

### 2.9 The Tax Strategy page

**In plain English.** The page a Save Tax user lands on. It shows a headline saving for the tax year, the recommended actions with "Saves £X a year" and a next-step link, a "Mark as done" on each, a Done group, and the allowance grid (the spouse's too for a married user). All three clients read one endpoint.

**Status:** Working on web this run (user 90: headline £1,274, four ways, the household panel, one individual action, mark-done moved it to "Done 14/09/2026"); Unverified on `/m` (code read; the API payload was read with curl) and iOS (code read) — with MB-42 and MB-45 raised — **Evidence:** `TaxStrategyController.php:31-119`; `TaxStrategyService.php`; `TaxStrategyCalculator.php:49-126`; `resources/js/views/TaxStrategy/TaxStrategyDashboard.vue`, `store/modules/taxStrategy.js`, `components/TaxStrategy/*.vue`; `resources/mobile/views/TaxStrategy.vue`; `ios-native/Fynla/Features/TaxStrategy/*.swift`; `ShowEndpointTest` (5), `CalculateEndpointTest` (5), `TaxStrategyComposedPlanTest` (2), `TaxStrategyCalculatorTest` (95), `TaxStrategyMathTest` (19), `TaxStrategyMathIsaSubscriptionsTest` (4), `TaxStrategySourceTest` (2) passed; screenshots `web-tax-strategy-01-full.png`, `web-tax-strategy-02-marked-done.png`.

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Route | `routes/api.php:381-383` | `GET /api/tax-strategy` and `POST /api/tax-strategy/calculate`, `auth:sanctum` |
| 2 | Controller | `TaxStrategyController::show()` `:31-67` | calculator payload plus `composed_plan`; every recommendation stamped with `recommendation_id = 'tax_' + type` and its completion from `recommendation_tracking` (`:80-111`); three milestone detections, best effort |
| 3 | Calculator | `TaxStrategyCalculator::calculate()` `:49-126` | branches on `users.household_calculation_mode`; thirteen strategy classes; the ISA pool re-allocated so one allowance is not counted twice; grids for the user and, when married, the spouse (dual-earner from `tax_strategy_household_inputs`, non-working from the same row) |
| 4 | Web | `TaxStrategyDashboard.vue:14-22` | `TaxYearHeader` (headline from `composed_plan.combined_annual_saving`), `HouseholdCoordinationPanel` (asset-shifting panel for `single_earner_couple`, coordination list for `dual_earner`), `StrategyRecommendationList` (non-household calculator recommendations with next-step and mark-done), `HouseholdView` or `AllowanceGrid` |
| 5 | `/m` | `TaxStrategy.vue:174-190` | personalised intro once completed; household card; `composed_plan.items` as the actions; headroom hero; allowance bars; "See all your actions" back to the dashboard |
| 6 | iOS | `TaxStrategyView.swift` (555 lines, transcribes `/m`) | `LiveTaxStrategyClient.load()` → `api/tax-strategy`; `markDone` → `api/recommendations/{id}/mark-done` |
| 7 | Mark done | `taxStrategyService.js:15-18`, `TaxStrategy.vue:236-250`, `TaxStrategyClient.swift:26-41` | `POST /api/recommendations/{id}/mark-done` with `module: tax`, then refetch |

Payload this run (user 90, `single_earner_couple`): user allowances Personal Allowance 12,570 used 12,570; Savings Allowance 500 used 500; ISA 20,000 used 4,000; CGT 3,000 (not known); Dividend 500 used 500; Pension Annual Allowance 60,000 used 6,000; Marriage Allowance not available (higher-rate). Spouse allowances: Personal Allowance, Savings Allowance 1,000, Starting Rate 5,000, CGT, Dividend all headroom; Marriage Allowance, ISA, Pension Annual Allowance 3,600 muted. Recommendations: `isa_topup_vs_psa` £262.40, `savings_to_spouse` £292, `non_earner_spouse_pension` £720, `gia_to_spouse` (no figure), `joint_savings_psa_split` £246. Composed plan total 1,274.4 (the conflict pair excluded £246).

**CRUD**

| Operation | Method and endpoint | Who may call it | Writes | Returns | Evidence |
|---|---|---|---|---|---|
| Read | `GET /api/tax-strategy` | signed-in; no tier gate in the route | milestone rows (side effect) | `tax_year`, `calculation_mode`, `user_allowances`, `spouse_allowances`, `recommendations`, `delta_vs_baseline`, `composed_plan` | curl this run |
| Recalculate | `POST /api/tax-strategy/calculate` | signed-in | nothing | the same payload with overrides applied | no caller (MB-42) |
| Complete | `POST /api/recommendations/{id}/mark-done` | signed-in | `recommendation_tracking` | | web this run |

### 2.10 Surfaces beyond the walk

**In plain English.** The campaign leaves traces on other screens: the Income pages show what the spouse section captured, the dashboard actions rank the campaign's module first, the activity feed names the campaign steps, and Fyn's ordinary chat is told the saved spouse figures when asked about them.

| Surface | File | What it does | Status |
|---|---|---|---|
| Web Income & Occupation | `IncomeOccupation.vue:477-499` | "Your spouse's income" card plus "What you told Fyn about your spouse" rows from `tax_strategy_household_inputs` | Broken for a non-working spouse (MB-48); dual-earner not driven |
| `/m` Income | `resources/mobile/views/Income.vue:74-86` | the same rows | same |
| Web Actions page | `ActionsDashboard.vue:4-12, 155-158` | "Your tax strategy" tile for `onboarding_fyn_selection === 'savetax'` | Dead end (MB-44); the actions list itself showed the tax items this run |
| `/m` dashboard | `NextActionsService.php:266-278` | campaign affinity on the ranked actions | Unverified (code, `CampaignAffinityTest` passed) |
| Fyn advice chat | `FynContextAssembler.php:177-185, 530-542` | `<saved_household_finances>` block when the user asks about spouse finances | Unverified |
| Web dashboard "Where to focus" | `GamifiedDashboard.vue:252, 286` | a "Save tax" tab routing to `/tax-strategy` | seen this run (tab present after registration) |
| iOS | `AuthModels.swift:145-180`, `SettingsModel.swift:86-90` | `active_campaign` decoded; `onboardingActive` mirrors the `/m` predicate | I COULD NOT TEST THIS (no simulator run) |

### 2.11 Tests for this section

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| `tests/Feature/AI/CampaignAuditFixesTest.php` | the pause/resume and re-entry audit fixes | yes | 16 passed |
| `tests/Feature/AI/CampaignReentry{Start,Dispatch,Exit}Test.php`, `OnboardingStartCampaignMapTest.php`, `tests/Feature/Onboarding/EntrySourceCampaignMapTest.php` | start endpoint, dispatch, exit | yes | 27 passed |
| `tests/Feature/Auth/CampaignRegistrationHandoffTest.php`, `FunnelAnswersCaptureTest.php`, `PensioncheckRegistrationPayloadTest.php` | § 2.3 | yes | 28 passed |
| `tests/Feature/Marketing/SaveTaxClaimClarityTest.php`, `tests/Feature/PublicPages/PensioncheckRoutesTest.php` | § 2.1, § 2.2 | yes | 13 passed |
| `tests/Feature/Onboarding/CampaignSpouseInviteTest.php`, `CampaignSynthesisTurnTest.php`, `FunnelRecapOnceTest.php`, `IncomeFunnelChallengeFlowTest.php`, `PensioncheckSynthesisTurnTest.php` | invite, synthesis, recap once, income challenge | yes | 27 passed |
| `tests/Unit/Services/Marketing/SaveTaxEstimateServiceTest.php`, `PensionEstimateServiceTest.php` | § 2.2 | yes | passed (23 + the pension file) |
| `tests/Unit/Services/Onboarding/Campaign*.php`, `Pensioncheck*.php`, `FunnelIncomeBandTest.php`, `IncomeFunnelChallengeTest.php` | state machine branches, sections, verify, bubbles, bands | yes | 205 passed |
| `tests/Unit/Services/Mobile/CampaignAffinityTest.php` | § 2.10 | yes | 4 passed |
| `tests/Feature/Api/TaxStrategy/*`, `TaxStrategyComposedPlanTest.php`, `tests/Unit/Services/Tax/TaxStrategy*.php`, `TaxStrategySourceTest.php` | § 2.9 | yes | 132 passed |
| `tests/Feature/AI/DirectWrite/Capture*.php` | the eight campaign tool handlers | yes | passed (in the 658) |
| `tests/Browser/scenarios/BS-26/27/28-savetax-*.php` | the three Save Tax paths | no | `markPendingInteractiveRun` — never run by Pest; BS-26 still describes sliders (MB-42) |
| `tests/E2E/journeys/user-reported-campaign-regressions.spec.js`, `tests/E2E/public/savetax-clarity.spec.js` | ten desktop and phone journeys; plan page clarity | no | target csjones or a running Playwright host |
| `tests/frontend/mobile/TaxStrategy.test.js`, `ios-native/FynlaTests/TaxStrategyTests.swift` | `/m` and iOS page rendering | no | not run |

The whole scoped Pest run: 658 passed, 95,560 assertions, 318 seconds, at `6b365dddc`.

Tests written in this run: none. The contracts this run broke (MB-37, MB-47, MB-48) are page-rendering gaps that need Playwright; the E2E suite already has the shape (`user-reported-campaign-regressions.spec.js`), and adding a spec that runs only against csjones would not be run here.

## 3. Findings

| Id | Status | Where | What is wrong | Evidence |
|---|---|---|---|---|
| MB-37 | Broken (web) | `/savings` during the savings verify | the page shows no accounts, so the verify is blind | § 2.5; screenshot `web-fyn-06-savings-verify-page.png` |
| MB-38 | Does not make sense + Duplicate | `pensioncheck.php:167-201`, `pensioncheck-plan.js:70-75`, `OnboardingStateMachine.php:2555-2575`, `AuthController.php:169`, `OnboardingChatDirector.php:3350` | Pension Check hardcodes the income bands and gets no income context or cross-check | § 2.1, § 2.3 |
| MB-39 | Does not make sense | `RedirectPhoneToMobile.php:44`, `router/index.js:264-286`, `CampaignPage.vue:39` | four "campaign" pages that lead to plain onboarding | § 2.1 |
| MB-40 | Does not make sense | `savetax-plan-v4.js:41-45` vs `SaveTaxEstimateService.php:87-160` | the headline counts lines the page hides | § 2.2 |
| MB-41 | Dead (route side) | `routes/api.php:244`, `TaxAllowancesController.php:90-94` | endpoint serves mock-ups only; mislabelled threshold | § 2.2 |
| MB-42 | Dead (route side) | `routes/api.php:383`, `taxStrategy.js:42-54` | recalculation endpoint has no caller | § 2.9 |
| MB-43 | Dead | `OnboardingStateMachine.php:127`, `OnboardingChatDirector.php:1223, 5795`, `GateRoutes.php:155`, `ActivityFeedService.php:49` | charitable-giving state remnants | § 2.5 |
| MB-44 | Dead end | `OnboardingStateMachine.php:1932-1938`, `ActionsDashboard.vue:4-12, 155-158`, `config/onboarding.php:84` | no way back after "No thanks"; the promised tile can never show | § 2.4, § 2.5 |
| MB-45 | Dead end + Duplicate | `StrategyRecommendationList.vue:101-120`, `TaxStrategy.vue:149-157`, `TaxStrategyView.swift:473-478`, `taxStrategy.js:87` vs `TaxStrategy.vue:174` | dead next-step routes on web; key drift on phone; two list sources | § 2.9 |
| MB-46 | Does not make sense (Rule 2) | corpus block `campaign_spouse_non_working_assets` | "£40,000" hardcoded in Fyn's prompt | § 2.5 |
| MB-47 | Broken (web) | web stores for investments and expenditure | pages under the chat not refetched after a Fyn write | § 2.5 |
| MB-48 | Broken | `UserProfileService.php:488-501` | spouse verify page empty for a non-working spouse | § 2.5, § 2.10 |
| MB-49 | Does not make sense | `handleCampaignVerifyEdit()` | natural correction produced no write; slow edit turn | § 2.5 |
| MB-50 | Does not make sense (`/m`) | `/m/app/expenditure` | verify screen shows a derived total, not the entered figure | § 2.8 |
| MB-51 | Does not make sense (copy) | `sectionLabel()`, corpus `campaign_pension_contribs`, `buildCampaignDobPrompt()`, `buildCampaignIntroPrompt()` | copy that does not fit the path | § 2.5, § 2.8 |
| MB-52 | Duplicate drift | `/m` Fyn text renderer | multi-paragraph advice collapsed | § 2.6, § 2.8 |
| MB-53 | Does not make sense | `campaignSections('pensioncheck')` | re-entry re-asks the pensions section | § 2.8 |
| MB-54 | Does not make sense | `campaign_occupational_scheme` on the Save Tax path | pot value and provider dropped by the deterministic backstop after a model refusal (cause corrected 2026-09-14) | § 2.5 |
| MB-55 | Does not make sense | `savetax-plan-v4.js:183` vs `pensioncheck-plan.js:463-469` | the Save Tax sign-in link drops the campaign | § 2.3 |

Reproduced from earlier runs: MB-27 (web expenditure verify shows £0) — this run, § 2.5. Confirmed unchanged: MB-09 (the Vue `/savetax` copy), MB-10 (the mock-up routes).

None found in this run: no Duplicate of the estimate services or the campaign tool handlers (each has one implementation); no Dead controller methods in the campaign perimeter beyond MB-41 and MB-42.

Observed, not raised as a bug: with the xAI provider (`grok-4.3`), delegated capture turns on web took between 30 seconds and 6 minutes 36 seconds this run (ISA turn about 5 minutes, savings turn about 6 minutes, the verify edit 6 minutes 36 seconds; the pension, investment and spouse turns under a minute). Nothing was logged during the slow turns. I COULD NOT determine whether this is the provider, the tool loop or the local machine.

## 4. Coverage and gaps

| Area | Checked | I COULD NOT VERIFY |
|---|---|---|
| Files in perimeter | 94 listed (routes, the eight public campaign pages and their scripts, the two estimate services, `FunnelIncomeBand`, `FunnelAnswersMapper`, `RegistrationHandoffService`, `PendingRegistration`, `RegisterRequest`, `AuthController` register and verify, `TaxAllowancesController`, `AiChatController` send and start, `config/onboarding.php`, the two migrations, the nine campaign tool schemas plus one xAI variant, the campaign blocks of the corpus, `OnboardingStateMachine.php:100-370, 446-722, 1025-1230, 1335-1600, 1665-1836, 1865-2388, 2388-2625`, `OnboardingChatDirector.php:1106-1560, 2077-2210, 3288-3560, 4173-4400, 4500-4540, 4597-4640, 4744-4800, 5539-5560, 5590-5787` plus a method index of the rest, `OnboardingPromptBuilder.php:95-210`, `AiToolDefinitions.php:405-470`, `CoordinatingAgent.php:1190-1215, 5454-5770, 5970-6120, 6230-6300`, `UserOnboardingStepObserver`, `RedirectPhoneToMobile.php:30-135`, `mobile-host.blade.php`, `UserProfileService.php:450-520`, `FynContextAssembler.php:175-190, 530-545`, `GateRoutes.php:145-165`, `NextActionsService` and `MilestoneDetectionService` campaign lines, `TaxStrategyController`, `TaxStrategyService`, `TaxStrategyCalculateRequest`, `TaxStrategyHouseholdInput`, `TaxStrategySource`, `TaxStrategyCalculator.php:1-240`, the two DTOs, the web Tax Strategy view, store, service and six components, `resources/mobile/views/TaxStrategy.vue`, `TaxStrategyClient.swift`, `TaxStrategyModel.swift`, `TaxStrategyView.swift:1-80` plus its key map, `AuthModels.swift:140-185`, `SettingsModel.swift:80-100`, `Register.vue:250-362, 495-575`, `Dashboard.vue:1375-1400`, `aiChat.js` and `aiChatService.js` campaign lines, `ActionsDashboard.vue`, `router/index.js` campaign and section routes, `Login.vue` redirect lines, `resources/mobile/views/Dashboard.vue:920-1000`, `mixins/onboardingChat.js:20-60, 155-230, 370-425, 690-720`, `MobileChrome.vue` campaign lines, `Income.vue:70-95`, `IncomeOccupation.vue:470-500`, `CampaignPage.vue` and `QuickStartPage.vue` call-to-action lines, the three BS docblocks, the E2E test names, the acceptance YAML) | Not read: the thirteen strategy classes and `TaxStrategyMath` (section 13); `TaxStrategyCalculator.php:241-401` (spouse grids); `TaxStrategyView.swift:80-555`; `TaxStrategyModels.swift` beyond its keys; `resources/mobile/utils/fynText.js`; `resources/mobile/views/Savings.vue`; `SpouseHouseholdPhrasings`; `CaptureAccuracyGate`; `OnboardingChatDirector.php:1560-1862, 1905-2077, 2210-2693, 3060-3288, 3560-4173, 4400-4500, 4540-4597, 4640-4744, 4800-5539, 5560-5590, 5805-6535` (indexed by method only; `handleAssetCaptureTurn` and `handleGroupedExtractTurn` were read in 02a); the v2, v3 and v4 mock-up pages and scripts beyond their script tags; `savetax-v2.js`; `docs/reference/tools/07-savetax-campaign.md` beyond its head (its schema for `capture_salary_sacrifice` is a version behind the corpus, `pension_id` is no longer required) |
| Tests | 658 Pest tests across the listed paths | BS-26, BS-27, BS-28 (never run by Pest); the two E2E specs; the Vitest and Swift Tax Strategy tests |
| Playwright | Web: Save Tax funnel with `utm_source`, plan page, compact registration, hand-off, verification, dashboard with Fyn, the whole married-non-working-spouse walk including the verify edit, the short-date confirm, the invitation decline, the terminal, the Tax Strategy page, Mark as done, `/pension`, `/investments`, `/actions`, sign out. `/m` at 390 px: `/m?to=/pensioncheck`, the six-question funnel framed, plan page, compact registration inside the frame, verification, the token entering `/m/app`, the whole self-employed single walk with every verify screen, the terminal, the retirement page, re-entry with `from=pensioncheck`, the recap, one gap-walk step | The Pension Check funnel at desktop width; the Save Tax walk on `/m`; the dual-earner spouse path; "Yes, invite them" (sends a real email from this machine); "No thanks" at the consent gate; "Something's changed" at the re-entry recap; the rest of the re-entry walk; the `/m` Tax Strategy screen rendering; production |
| Surfaces | Web and `/m` driven; iOS read (`TaxStrategyClient`, `TaxStrategyModel`, `TaxStrategyView` head, `AuthModels`, `SettingsModel`) | I COULD NOT TEST THIS on iOS (no simulator run; both native schemes point at production, `ios-native/CLAUDE.md`) |

Test data left behind, all password `MapTest2!`: user 90 `map-camp-w-2026-09-14@example.com` (completed Save Tax; married, `single_earner_couple`; savings accounts 237-238, investment account 123 at £27,000, dc pension 71, `tax_strategy_household_inputs` row 1, `recommendation_tracking` `tax_isa_topup_vs_psa` completed, one Sanctum token `map-camp`); user 91 `map-camp-m-2026-09-14@example.com` (completed Pension Check then re-entered; mid re-entry at `campaign_pension_contribs` with `active_campaign = pensioncheck`; dc pension 72, db pension 32, state pension 70, retirement profile, three `pension_input_history` rows, two conversations, tokens `map-m` and the `/m` login token). Nothing depends on either. The local `.env` sends real email; each registration sent a code email to an example.com address. The Playwright tab is left on `/m/app/dashboard` signed in as user 91 with the web session signed out; `sessionStorage['fynla.signup_source']` in that profile is `linkedin` (first-touch), which is why user 91's `signup_source` is `linkedin` although the Pension Check funnel was opened with `utm_source=instagram`.

## 5. Glossary

| Term | Meaning in this section |
|---|---|
| Campaign | One of the two marketing entry points, `savetax` or `pensioncheck`, as keyed in `config/onboarding.php` `campaign_map`. Not the Marketing Pipeline "campaigns" admin table, and not the lifecycle email campaigns. |
| Funnel | The public questionnaire page (`/savetax`, `/pensioncheck`) that collects banded answers before registration. |
| Funnel answers | The JSON object of those answers, stored on `pending_registrations.funnel_answers` and copied to `users.funnel_answers` at verification. |
| Income band | One of five stable keys (`zero`, `upto_50270`, `50271_100000`, `100001_125140`, `over_125140`) whose pound boundaries are resolved from the tax configuration at run time. |
| Income context | The pound boundaries and labels of a band at the moment the funnel was submitted, stamped alongside the band so a tax-year rollover cannot change what the person was shown. |
| Hand-off token | An encrypted, fifteen-minute token identifying the pending registration (or a restorable deleted account) and the campaign, carried from the public page to the web app in the URL. |
| Section walk | The ordered list of sections a campaign asks about; each section has an entry state and may be skipped as a whole. |
| Verify loop | The announce, navigate, confirm or edit sequence that follows every section capture (mapped in 02a § 2.5). |
| Advice turn | An automatically advancing Fyn message that voices up to two composed-plan items for the section just confirmed. |
| Synthesis | The closing advice turn that voices the whole composed plan in order. |
| Re-entry | A completed user starting a campaign walk again; only Pension Check allows it. |
| Terminal | The last state of a walk; it carries the route the person is offered (Tax Strategy or Retirement). |
| Tax Strategy page | The `/tax-strategy` screen on all three clients: allowance grid plus recommended actions. |
| Household calculation mode | `users.household_calculation_mode`: `single`, `dual_earner` or `single_earner_couple`; decides which spouse states run and which grids the Tax Strategy page shows. |
