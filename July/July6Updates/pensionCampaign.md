# Pension Campaign (/pensioncheck) — Full Map & Audit

*2026-07-06. The complete current-state map and audit of the pension campaign (campaign #2, PRs #607–#610): homepage CTA, pre-registration funnel, registration, pull-through, the re-entry substrate, the Fyn question decision trees for BOTH user classes, and the eventuality map. Grounded file:line against the dev tip (`6f965f1`) via the `fynla-fixes` worktree — line numbers indicative, `path:method` primary. Companion docs: `July/July3Updates/pension-campaign-map.md` (design), `pension-campaign-plan.md` (build plan), `July/July4Updates/pensioncheck-patch-notes-technical.md` (what shipped), `July/July6Updates/saveTax.md` (campaign #1 — the shared machinery is documented in full there; this doc focuses on what pensioncheck adds/changes).*

**The two user classes:**
- **New user** — arrives via the public funnel, registers, runs the full pension-lean walk (~12–14 questions).
- **Existing user** — already completed SaveTax onboarding (`onboarding_completed = true`); **re-enters** via `from=pensioncheck`, gets a recap of held data, answers only the gaps (~7 questions in the verified E2E walk). `onboarding_completed` is never touched.

---

## 1. The end-to-end flow (one page)

```
NEW USER                                        EXISTING USER (completed SaveTax)
Homepage "Where is your pension heading?"        Plan-page "Already with Fynla? Log in"
  → FUNNEL /pensioncheck (6 questions)             → /login?redirect=/dashboard?openFyn=journey
      employment · income · age · pensions            &from=pensioncheck
      (multi) · pot · spouse                       → login + MFA → Dashboard openFyn handler
      → localStorage['pensioncheck_answers']       → POST onboarding/start (from=pensioncheck)
        (campaign:'pensioncheck' baked in)             conditional 409 BYPASSED (reentry:true)
  → PLAN /pensioncheck/plan                            active_campaign='pensioncheck' stamped
      PensionEstimateService: projected pot            step=campaign2_existing_recap
      by State Pension age + contribution          → "Welcome back… here's what I already
      assumed + tax-relief note                        have from you… Is that all still right?"
  → register card "Start my pension plan — free"   → [Yes] → DELTA WALK (gap questions only,
      POST /api/auth/register (+funnel_answers)        data-presence skips do the routing)
  → verify → /register?from=pensioncheck
  → dashboard?openFyn=journey&from=pensioncheck
  → onboarding/start → entry base_work
                    BOTH → the SAME section walk: income → pensions → state_pension
                           → retirement_goals → spouse → expenditure
                           (skip predicates produce the delta for existing users)
  → per section: capture → gate → verify announce → navigate → confirm → advice → next
  → SYNTHESIS  "Here's your pension picture…" (mirrors the composed RETIREMENT plan)
  → TERMINAL   campaign2_terminal "We've built your pension picture, {first_name}."
      [Take me to my retirement plan → /retirement]
      (web: /retirement → /net-worth/retirement redirect; /m: native /retirement)
      existing user: completed_at untouched, no double awards; active_campaign cleared
  → POST-CAMPAIGN  applyCampaignAffinity('pensioncheck'→'retirement') — retirement
      actions first on the /m dashboard (see audit: reverts for re-entrants)
```

---

## 2. Homepage CTA

`public/pages/index.php:30-45` — the `feature-pensioncheck` card computes a **server-side representative figure** by calling `PensionEstimateService::estimate()` with a hard-wired median-ish profile (`full-time`, `upto_50270`, `40s`, `['workplace']`, `25k_100k`, `no`). Wrapped in try/catch — **a service throw renders the card without the figure, never a broken page**. Output `htmlspecialchars`-escaped.

Copy: headline **"Where is your pension heading?"**, sub "Answer six quick questions — no account needed — and see the pot you're on course for.", CTA **"Check my pension"** → `/pensioncheck`. Sits beside the savetax card, same visual pattern (a "distinguishing" gradient was reverted in review per Rule 16).

---

## 3. Pre-registration — the funnel (`/pensioncheck`)

**Files:** `public/pages/pensioncheck.php` + `public/pages/js/pensioncheck.js` (styles reuse `savetax.css`). Sequence: `employment → income → age → pensions → pot → spouse`.

### 3.1 The six questions (exact copy)

| # | Question | Sub-copy | Key | Options |
|---|---|---|---|---|
| 1 | "What is your employment status?" | "We use this to tailor your pension projection." | `employment` | `not-employed`, `part-time`, `full-time`, `self-employed`, `retired` |
| 2 | "What is your annual income?" | "Your gross income before tax, including salary, self-employment, and pension income." | `income` | `upto_50270`, `50271_100000`, `100001_125140` (badge **"Tax-trap zone"**), `over_125140` |
| 3 | "How old are you?" | "We use this to estimate how many years of contributions remain before retirement." | `age` | `under_30`, `30s`, `40s`, `50s`, `60_plus` |
| 4 (multi) | "Which types of pension do you have?" | "Select all that apply. This helps us understand your existing pension arrangements." | `pensions[]` | `workplace`, `personal_sipp`, `final_salary`, `none` (**`none` is exclusive** — selecting it clears the others) |
| 5 | "What is the total value of your pension pot?" | "An approximate figure is fine. Include all pensions you have." | `pot` | `none`, `under_25k`, `25k_100k`, `100k_250k`, `over_250k` |
| 6 | "Do you have a spouse?" | "Couples may be able to plan their pensions together to reduce their overall tax bill." | `spouse` | `yes` / `no` |

### 3.2 Behaviour

- Answers object initialised `{ campaign: 'pensioncheck', employment: null, income: null, age: null, pensions: [], pot: null, spouse: null }` — **campaign stamp baked in at line 4**.
- Single-selects auto-advance 220ms; only the pensions multi shows Continue; zero pension selections valid.
- `persistAndGoToPlan()`: `localStorage['pensioncheck_answers']` (try/catch) + navigate to `/pensioncheck/plan?from=pensioncheck&employment=…&income=…&age=…&pensions=a,b&pot=…&spouse=…` — query params exist for the server-side estimate.

---

## 4. Pre-registration — the plan page (`/pensioncheck/plan`)

**Files:** `public/pages/pensioncheck-plan.php` + `public/pages/js/pensioncheck-plan.js` + `app/Services/Marketing/PensionEstimateService.php`.

### 4.1 Server-side estimate

- Preamble reads the six `$_GET` keys (`pensions` split on comma, capped at 8). Direct-visit fallback = the representative persona (page never empty for SEO/shared links). try/catch → `null`.
- **`PensionEstimateService::estimate(array): array`** — banded, deterministic:
  - Band midpoints (private consts, explicitly "marketing midpoints, not tax values"): age `under_30`→25 … `60_plus`→63; pot `none`→0 … `over_250k`→300,000; income `upto_50270`→30,000 … `over_125140`→150,000.
  - Contribution assumption: **`AUTO_ENROLMENT_TOTAL_PCT = 0.08`** (statutory total) → monthly = `income × 0.08 / 12`; **zero for retired/not-employed**.
  - Growth: **`REAL_GROWTH_RATE = 0.025`** real p.a.; horizon = `max(0, retirementAge − ageMidpoint)`; `projectPot()` = standard future value (monthly compounding).
  - **TaxConfigService (Rule 2):** retirement age from `pension_allowances.state_pension.future_spa` (67); `taxReliefNote()` branches on `income_tax.higher_rate_threshold` / **`personal_allowance_taper_threshold`** (the review-caught Critical: the 60%-taper note cites the £100k taper start, NOT the higher-rate threshold) / `additional_rate_threshold`.
  - Returns `{projected_pot, retirement_age, years_to_retirement, monthly_contribution_assumed, tax_relief_note, already_retired}`.
- **Injection:** `window.PENSIONCHECK_ESTIMATE = <?= json_encode($est, HEX flags) ?>` — `</script>` breakout impossible; **query values are never echoed** (XSS-clean); failed estimate injects literal `null` and the JS falls back to static markup defaults (£140,000 / age 67). All dynamic strings pass `esc()` before `innerHTML`.

### 4.2 Register card + the re-entry hook

- Form: first/last name, email, password; button **"Start my pension plan — free"**; note "Takes you straight to your dashboard with Fyn open, ready to build your pension plan."
- `wireRegister()`: CSRF prime → `POST /api/auth/register` with `funnel_answers: realFunnelAnswers()` (raw localStorage, **never the demo persona**, null for direct visitors) → success stashes `fynla_pending_verify` → `/register?from=pensioncheck` (reuses the tested Vue verify modal).
- **"Already with Fynla? Log in"** (`wireLoginLink()`): href = `/login?redirect=` + encoded `/dashboard?openFyn=journey&from=pensioncheck` — **this link is the ONLY existing-user re-entry entry point in the product today** (see audit).

### 4.3 Routes

`routes/web.php:617-642` — `/pensioncheck/plan` declared before `/pensioncheck`, both before the SPA catch-all (pinned by `PensioncheckRoutesTest`), both behind `redirect.authed` (logged-in users bounce to the dashboard — re-entry must use the login link). `RebasePublicPageUrls` (global) handles the csjones `/fynla` subdirectory + injects `window.FYNLA_BASE`.

---

## 5. Registration + pull-through

Same pipeline as SaveTax (see `saveTax.md` §5) — differences only:

- **`RegisterRequest`** additionally validates the pensioncheck-only keys: `age` (max:20), `pensions` array + `pensions.*` (max:30), `pot` (max:20). `funnel_answers.campaign` is `string|max:40` — **no enum/allowlist** (unknown values fall through the campaign_map lookup harmlessly).
- **`FunnelAnswersMapper`** seeds `employment_status` and `marital_status` (spouse yes→married). The `spouseIncome` branch no-ops (pensioncheck never asks it). `age`/`pensions`/`pot`/`campaign` live only inside `users.funnel_answers`.
- **Desktop handoff** identical: `funnelHandoff` panel → verify → `dashboard?openFyn=journey&from=pensioncheck` → `startOnboardingConversation({from})`.
- **/m:** the mixin now **forwards `from`** (`onboardingChat.js:79-93`, PR #607 — was hardcoded `{}`); `Dashboard.vue initFyn()` passes `$route.query.from`. When `from` is lost (iframe→/m/app handoff drops the query by design) the **server-side funnel fallback** keys the campaign off `funnel_answers['campaign']='pensioncheck'`.

### 5.1 Pensioncheck `funnel_answers` key → consumer map

| Key | Consumers |
|---|---|
| `campaign` | `startOnboarding` funnel fallback; `NextActionsService::applyCampaignAffinity` (`CAMPAIGN_AFFINITY['pensioncheck']='retirement'` — retirement items first on /m dashboard); `MilestoneDetectionService` presence checks; `PendingRegistration` preservation |
| `employment` | `PensionEstimateService` (zero-contribution branch); mapper → `employment_status`; recap bullet |
| `income` | estimate (midpoint, relief note); recap bullet; income cross-check (`detectIncomeFunnelMismatch` via `FunnelIncomeBand`) |
| `age` | **estimate only** — validated, stored, **no post-registration consumer** (the walk captures real DOB) |
| `pensions` | recap bullets (`$pensionTypeMap`) + F12 time estimate (3 min + 1/extra type); NOT used for skip gating — `nextFromCampaignDob` hard-routes pensioncheck into the pension states precisely because `funnelHasAnyAsset` reads `assets`, which pensioncheck never produces |
| `pot` | **estimate only** — validated, stored, **no other reader** (real per-scheme values captured at `campaign2_pension_pots`) |
| `spouse` | mapper → `marital_status` (drives the spouse-section skip); recap bullet; estimate accepts but ignores it |

---

## 6. The re-entry substrate (PR #607) — the architectural addition

### 6.1 `users.active_campaign`

Migration `2026_07_03_000001_add_active_campaign_to_users.php` — `string(32) nullable`. Deliberately **a column, not a JSON context key** — the dispatch guard reads it on every message.

### 6.2 The dispatch predicate (canonical, amended in `00-canonical.md`)

`AiChatController::routesToOnboardingDirector()` (`:898-903`), ONE helper shared by all THREE seams (`sendMessage:243`, `streamQueuedMessage:416`, `action:823`):

```php
return ($user->onboarding_completed === false || $user->active_campaign !== null)
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);
```

A paused mid-campaign user (`active_campaign` set, step null) routes to **advice** — pinned by `CampaignReentryDispatchTest`.

### 6.3 `onboarding/start` for a completed user

`config/onboarding.php`: `'pensioncheck' => ['selection'=>'pensioncheck', 'entry'=>'base_work', 'reentry'=>true, 'reentry_entry'=>'campaign2_existing_recap']` (savetax stays `reentry:false`).

Order inside `startOnboarding` (`:576-783`):
1. Consent 403.
2. **`$reentryCampaign` resolved BEFORE the completed gate** — non-null only when `from` names a `reentry:true` campaign.
3. **Conditional 409**: `onboarding_completed === true && $reentryCampaign === null` → 409. So `from=pensioncheck` bypasses; `from=savetax` or bare start still 409s.
4. Flag 503, preview 403.
5. **Resume branch**: step non-null → SSE `{type:'resume', conversation_id, current_step}` — a mid-campaign re-entrant resumes, never restarts.
6. **Fresh re-entry**: conversation `metadata = {source:'fyn_onboarding', campaign:'pensioncheck'}`; `path='campaign'`, `selection='pensioncheck'`; entry step = **`reentry_entry` (`campaign2_existing_recap`) for completed users, plain `entry` (`base_work`) for fresh users** (even though the campaign is reentry-enabled); **`active_campaign='pensioncheck'` stamped** whenever `from=pensioncheck` matched (incl. fresh users — harmless, cleared at every exit; NOT stamped on the funnel-fallback path since `$reentryCampaign` derives only from `from`).
7. `onboarding_started_at` never overwritten on re-entry.

### 6.4 Exits — both clear `active_campaign`

- **Terminal** (`emitTerminalNavigationTurn:3387-3406`): clears `active_campaign` + all `onboarding_fyn_*` scratch **unconditionally**; `onboarding_completed`/`completed_at`/`recordProgress` gated on `$wasAlreadyCompleted` captured pre-mutation — **re-entry never resets `completed_at` or double-awards** (D3 E2E: byte-identical through two re-entries, terminal award count stayed 1).
- **"Something else" pause** (`handleSomethingElseAction:519-544`): parks `paused_at_step`, nulls step, **nulls `active_campaign` unconditionally** → next message routes to Advice Fyn. Response: "Of course — what can I help you with?"
- `emitDoneTurn` (the non-campaign terminal) also nulls it defensively.

Pinned by `CampaignReentry{Start,Dispatch,Exit}Test`.

---

## 7. The walk — entry and section machinery

### 7.1 New-user entry (turn 1 at `base_work`)

`buildPensioncheckFunnelRecapPrompt` (`OnboardingStateMachine:2498-2565`), once per conversation:

> Hi {firstName}, I'm Fyn — thanks for those answers. Here's what you've told me:
> - *(employment / income band / spouse / pension types: "a workplace pension", "a personal or self-invested personal pension", "a final salary or career average pension")*
>
> I've started your profile from what you told us, and to get a clear picture of your pension position I just need a few more details — this usually takes about {3 + extra pension types} minutes.
> *(BUBBLE_BREAK)*
> **Let's start with your income.** Tell me your gross annual income (this includes bonuses and commissions).

(Savetax differs only in the goal line: "to build your personalised tax plan".) Non-funnel arrivals at `base_personal` would get `campaignWelcomeFor('pensioncheck')` = "welcome to Fynla — let's get a clear picture of your pension."

### 7.2 Section order + skips (the delta engine)

`CAMPAIGN_SECTION_ORDERS['pensioncheck']` = `income → pensions → state_pension → retirement_goals → spouse → expenditure`. Savings/investments deliberately excluded (D3, pension-lean).

| Section | Entry state | Whole-section skip (data-presence — this IS the existing-user delta) |
|---|---|---|
| income | `base_employment` | `skipSectionIfIncomeKnown` — `annual_employment_income > 0 OR annual_self_employment_income > 0`. **Keyed on captured income columns, NOT `employment_status`** — the mapper seeds `employment_status` for every funnel registrant, so keying on it would have skipped income for every NEW user (review-caught Critical, commit 0890212) |
| pensions | `campaign_dob` | none — per-state gates do the work |
| state_pension | `campaign2_state_pension` | `skipSectionIfStatePensionKnown` — `statePension()->exists()` (row-existence) |
| retirement_goals | `campaign2_retirement_goals` | `skipSectionIfGoalsKnown` — profile has BOTH `target_retirement_age` AND `target_retirement_income` |
| spouse | `campaign_spouse_work` | `skipIfNotMarried` |
| expenditure | `base_expenditure` | `skipSectionIfExpenditureKnown` — `monthly_expenditure > 0 OR annual_expenditure > 0` |

**Verify config** (`campaignVerifyConfig('pensioncheck')`): income→`/income`, pensions→`/retirement`, state_pension→`/retirement`, retirement_goals→`/retirement`, spouse→`/income`, expenditure→`/expenditure`. Savetax arrays are byte-identical (characterisation-pinned).

**Delegated-turn tools** — `toolsForFocus('pensioncheck')` = `create_pension, capture_salary_sacrifice` + `update_profile, update_record`. **This arm is the PR #610 root-cause fix**: without it the focus fell to the savings default and the live model security-refused every pension answer.

---

## 8. The walk — full state table (pensioncheck order)

States reused from savetax are summarised; see `saveTax.md` §9 for their full anatomy. New `campaign2_*` states in full.

### Income (reused)
`base_work` (funnel recap variant above) → `base_employment_more` (the ONE gate) → verify(`/income`) → **advice: SILENT** — 'income' is not in `PENSIONCHECK_SECTION_STRATEGY_TYPES`, and non-mapped sections return null so **no pensioncheck path can reach a savetax tax builder** (review-caught cross-campaign leak, commit dea2b8a).

### Pensions
**`campaign_dob`** (reused; skip if DOB set) — pensioncheck funnel users have no `assets` key so they get the neutral "Next, **what's your date of birth?**…" variant; short-format confirm-back applies. → **always** `campaign_occupational_scheme` (`nextFromCampaignDob` pensioncheck override — `funnelHasAnyAsset` reads `assets`, which pensioncheck never produces, and would have wrongly skipped the whole pension walk).

**`campaign_occupational_scheme`** (reused; `advance_on_answered_question`) — "Tell me about your workplace pension. **What percentage of your salary do you contribute, does your employer match it, and is it via salary sacrifice?**…" **Pensioncheck-extended skip**: `skipIfNotEmployed` OR `PensionStore::hasWorkplaceDcPensionWithValue()` (workplace DC row with `current_fund_value > 0` — an existing user whose savetax walk left a valued workplace pension skips this; savetax path byte-identical). → `campaign2_pension_pots`.

**`campaign2_pension_pots`** (NEW; delegated, looping) — **the pot-value backfill**, usually the existing user's first gap question (savetax never captured pot values):
- Prompt names the scheme: "**What's the current value of your {scheme} pension?** A rough figure from your latest annual statement or provider app is fine — for example £45,000 or 45k." (`firstDcPensionMissingPotValue` — first DC row with `current_fund_value <= 0`; the `<= 0` sentinel replaced a `whereNull` dead-query caught in review).
- **Entry skip**: no DC pension missing a value → skip (prevents the generic fallback prompt letting the model ad-lib).
- `record_context:'pensions'` appendix lists DC pensions with `entity_id`s → `update_record` targets `current_fund_value` by id.
- **Loop**: another pot still missing → self; all valued → advance; **"not sure"/"don't know"/"skip"/"no idea" tokens exit the loop** (never traps).
→ `campaign_pension_contribs`.

**`campaign_pension_contribs`** (reused; `record_context_mode='contribution'`) — "Beyond the workplace pension we covered, **do you make any personal pension or Self-Invested Personal Pension contributions?…**" With existing personal/SIPP/stakeholder pensions the appendix instructs: *update that pension's `monthly_contribution_amount` by `entity_id` — do NOT create a new pension* (the D5 fix: "£200 a month" landed as an update, truthful ack, no duplicate row). → `campaign2_pension_db`.

**`campaign2_pension_db`** (NEW; delegated; `advance_on_answered_question`) — "**Do you have any final salary or career average pensions — the kind that pay a guaranteed income rather than building a pot?** If so, tell me the scheme name and the yearly pension you've built up so far." `create_pension` writes `db_pensions` (`accrued_annual_pension`). → `campaign_pension_history`.

**`campaign_pension_history`** (RESTORED pensioncheck-only; grouped_extract, `capture_pension_history`) — "**Roughly how much has gone into your pensions in each of the last three tax years?** Rough figures are fine…"
- **The higher-rate gate** (`skipIfPensionHistoryNotApplicable`): skip when gross income ≤ `TaxConfigService->get('income_tax.higher_rate_threshold', 50270)` — carry-forward advice only helps people who could out-contribute the standard Annual Allowance. **Savetax keeps its June #586 removal** — this state is reachable only via `campaign2_pension_db`.
- `clarify_single_figure`: one lone figure with no per-year/total cue → "Just to be sure I read that correctly — is that the total across the three tax years, or roughly that amount each year?…"
- Writes `PensionInputHistory` rows per tax year.
→ `campaign2_flexible_access`.

**`campaign2_flexible_access`** (NEW; delegated) — "**Have you taken any money out of a pension — a lump sum or a regular income?** It matters because it can cap what you're allowed to pay in from now on."
- **Skip**: DOB known AND age < 55 (Minimum Pension Access Age — see audit: hardcoded), OR `has_flexibly_accessed` already true. Null DOB → ask (fail-open).
- "Yes" → `update_record` sets `dc_pension.has_flexibly_accessed` (allowlisted in `UpdateRecordAllowlist` — PR #610 closed the latent gap where the yes-branch couldn't persist); "No" → `advance_on_answered_question`.
→ **pensions verify** (`/retirement`) → **`campaign_advice_pensions`** — composed **retirement** plan items (§9).

### State Pension
**`campaign2_state_pension`** (NEW; grouped_extract, `capture_state_pension`; section skip on row-existence) — "**Do you know your State Pension forecast?** You can check it in a couple of minutes on the government's Check your State Pension service. If you have it, tell me the yearly amount and how many qualifying years you've built up."
- Retry: "…If you're not sure, just say so and we'll note the gap."
- `advance_on_answered_question` — **"not sure" advances**; the engine's no-forecast advice fires later.
→ verify (`/retirement`) → advice state exists but its type list is EMPTY → always silent → next section.

### Retirement Goals
**`campaign2_retirement_goals`** (NEW; grouped_extract, `capture_retirement_goals`; section skip when both fields set) — "**When would you like to retire, and what yearly income would feel comfortable?** Rough numbers are fine — for example 65 and £30,000."
- **NO `advance_on_answered_question`** — the tool must land (or partial-retry): the goals are the campaign's centrepiece.
→ verify (`/retirement`) → **`campaign2_advice_retirement_goals`** (`plan_retirement_income`).

### Spouse
**`campaign_spouse_work`** (reused; skip if not married OR mode known — every savetax graduate skips) → **pensioncheck routes BOTH earner modes to `campaign2_spouse_pensions`** — the savetax household-tax states never run for a pensioncheck user.

**`campaign2_spouse_pensions`** (NEW; delegated; `advance_on_answered_question`) — "**Does your spouse have pensions of their own?** Tell me the type and a rough value for each — workplace, personal, or final salary." `create_pension` with spouse ownership. → verify (`/income`) → advice SILENT → expenditure.

### Expenditure (reused)
`base_expenditure` (skip if set — every savetax graduate skips) → verify (`/expenditure`) → no advice → sections exhausted → synthesis.

---

## 9. `campaign2_existing_recap` — the re-entry gate (in full)

State: bubbles `[Yes, that's right | Something's changed]`; prompt `buildExistingRecapPrompt` (`OnboardingStateMachine:2254-2327`) — **built entirely from live DB reads** (F5 spirit: deterministic values, never model paraphrase):

> Welcome back, {firstName}. Let's take a proper look at your pension. Here's what I already have from you:
> - Annual income £62,000
> - Aviva Workplace (£45,000 pot, 5% employee contribution)   *(one line per DC pension; parts only when > 0)*
> - NHS 2015 (£8,200 a year accrued)   *(one line per DB pension)*
> - Married to Angela
>
> **Is that all still right?**

(A fresh user reaching it defensively gets the neutral lead "Right, {firstName} — before we go on, here's what I have so far:".)

**Branching (`nextFromExistingRecap`):**
- **"Yes, that's right"** (and any unmatched answer) → walk the section order from the top, enter the **first non-skipped** section (skips applied transitively) — **this produces the delta walk**.
- **"Something's changed"** → `campaign_verify_edit` — see AUDIT FLAG P2: this path never stamps `verify_section`, and after one landed edit + confirm the walk routes to **synthesis → terminal**, bypassing the gap questions.

---

## 10. The two new capture tools

Both: corpus `.md` + **`.xai.md` (LIVE — the app runs xAI)** in `fyn-memory/procedural/tool_schema/campaign/`; registered in `AiToolDefinitions::ORDER['campaign']`; dispatched in `CoordinatingAgent`; **listed in `AdviceFyn::WRITE_TOOLS`** (advice mode strips them). Nuance: neither is in `handleInlineCapture`'s `captureToolSet()` — they are armed only on their grouped-extract states (see audit P8).

### 10.1 `capture_retirement_goals` (`CoordinatingAgent::handleCaptureRetirementGoals`)

Schema (the behaviour-governing description): *"Record the user's retirement goals. Call when the user states a target retirement age and/or a desired yearly retirement income. Ages are whole years between 55 and 75; income is a gross yearly figure in pounds. **Never guess a value the user did not state — omit the parameter instead.**"*

- Validation: both null → error; age outside 55–75 → error; negative income → error.
- Existing profile → update supplied fields only. No profile + age → `RetirementProfile::create` (merging any **parked income** first).
- **Income-only answer** (cannot create — age required by the profile): income **parked** in `ai_conversations.onboarding_parked_facts` and the handler returns the `details.missing => ['target_retirement_age']` receipt → partial retry: "Thanks — I still need the age you would like to retire at. Could you share that?" — the parked income survives the retry and merges when the age arrives (the review-caught F5 silent-drop fix).
- **`users.target_retirement_age` sync** — the /retirement projection/readiness layer reads the users column, not the profile row (PR #610: leaving it null showed the default 67 and kept the checklist item outstanding).
- **Cache bust** — `invalidateUserCache()` forgets `retirement_analysis_{id}` / `retirement_projection_` / `retirement_income_` (file cache driver has no tag support; the tag flush silently no-ops — PR #610: the stale mid-walk analysis previously yielded an empty synthesis).

### 10.2 `capture_state_pension` (`CoordinatingAgent::handleCaptureStatePension`)

Schema: *"…The forecast is a yearly figure in pounds (convert a weekly figure by multiplying by 52 and say so in your reply). Omit anything the user did not state. **If the user does not know their forecast or is unsure, do NOT call this tool at all — never pass 0 for a figure the user did not give. Never infer the State Pension age from another pension (a workplace or NHS scheme's normal retirement age is NOT the State Pension age).**"*

- **Hallucination write-guard**: a `state_pensions` row is written ONLY on `forecast_annual > 0 OR ni_years_completed > 0` — `state_pension_age` alone never justifies one (real incident: the model invented `state_pension_age=60` from an NHS answer). No positive signal → no write, `advance_on_answered_question` moves on.
- Writes `StatePension::updateOrCreate` (`state_pension_forecast_annual`, `ni_years_completed`, `state_pension_age`); same cache bust.

---

## 11. Advice, synthesis, terminal

**`PENSIONCHECK_SECTION_STRATEGY_TYPES`** (`OnboardingChatDirector:911-915`):

| Section | Retirement strategy ids voiced |
|---|---|
| pensions | `increase_pension_contribution`, `salary_sacrifice_pension`, `carry_forward_unused_allowance` |
| state_pension | *(empty — NI-gap/no-forecast advice is agent-sourced, not in the strategy catalogue; turn fires silently)* |
| retirement_goals | `plan_retirement_income` |

Mapped sections voice via `buildRetirementSectionAdvice` — composed **retirement** plan (`ComposedModulePlanService::forSource(RetirementStrategySource)`), max 2 items, claim-tier prefix, closing "I've added this to your actions list to come back to later." **Non-mapped sections return null — silent** (no savetax builder reachable).

**Synthesis** (`buildSynthesisAdvice`, campaign-aware): `$user->refresh()` → composed RETIREMENT plan → "**Here's your pension picture, built from what you told me — in the order I'd tackle it:**" + bullets (retirement items carry no `estimated_annual_tax_saved`, so no £-suffix and no combined-savings line) + the FCA signpost. Degrade: "That's your pension details saved, {firstName}. You'll find your full retirement picture on the next screen." Savetax output byte-identical (PR #592 pin strengthened).

**`campaign2_terminal`** — "**We've built your pension picture, {first_name}.**" + [**Take me to my retirement plan** → `/retirement`] (campaign-aware CTA). Web resolves via the `router/index.js` redirect `/retirement → /net-worth/retirement` (PR #610 — the button previously hit the NotFound catch-all); /m defines `/retirement` natively. Side effects per §6.4 (guarded for re-entrants).

**Post-campaign affinity**: `applyCampaignAffinity` resolves campaign as `onboarding_fyn_selection → funnel_answers['campaign'] → legacy savetax default`, then sorts `module==='retirement'` items first. **Fresh pensioncheck users keep retirement-first forever** (their funnel stamp says pensioncheck). **Re-entrants revert to tax-first at the terminal** — their funnel stamp says savetax and the selection column is cleared (patch-notes known item 3; needs a durable signal if retirement-first should persist).

---

## 12. Decision trees

### 12.1 New user — full walk

```
homepage CTA → /pensioncheck (6 Qs) → /pensioncheck/plan (projected pot) → register
  → verify → dashboard?openFyn=journey&from=pensioncheck → onboarding/start
  → step=base_work (active_campaign stamped when from= survived; funnel fallback otherwise)

INCOME      base_work: funnel recap + "**Let's start with your income.**"
            ├ band mismatch → challenge [Continue|Change]
            → base_employment_more "other roles?" ─No→ VERIFY(/income) → advice SILENT
PENSIONS    campaign_dob "**what's your date of birth?**" [confirm-back on short formats]
            → campaign_occupational_scheme (skip: not employed / valued workplace DC exists)
              "workplace pension: %, match, salary sacrifice?" ─"none"→ advance
            → campaign2_pension_pots (skip: no pot missing a value)
              "**What's the current value of your {scheme} pension?**"
              ├ value → update_record → next missing pot | all valued → advance
              └ "not sure"/"skip" → advance (loop escape)
            → campaign_pension_contribs "personal pension or SIPP contributions?"
              (existing personal pension → update by id, never duplicate)
            → campaign2_pension_db "**final salary or career average pensions…?**"
            → campaign_pension_history (SKIP unless income > higher-rate threshold)
              "**how much has gone into your pensions in each of the last three tax years?**"
              ├ single ambiguous figure → "total across three years, or each year?"
            → campaign2_flexible_access (SKIP if age<55 or already flagged; null DOB → ask)
              "**Have you taken any money out of a pension…?**"
              ├ yes → update_record has_flexibly_accessed | no → advance
            → VERIFY(/retirement) → advice(pensions): ≤2 retirement strategies + actions line
STATE PEN   campaign2_state_pension (SKIP if state_pensions row exists)
              "**Do you know your State Pension forecast?**"
              ├ figures → capture_state_pension (write-guard: forecast>0 OR ni_years>0)
              └ "not sure" → no tool call → advance, gap noted
            → VERIFY(/retirement) → advice SILENT
GOALS       campaign2_retirement_goals (SKIP if both targets set)
              "**When would you like to retire, and what yearly income would feel comfortable?**"
              ├ age+income → profile write + users.target_retirement_age sync + cache bust
              ├ income only → parked + "I still need the age…" → age arrives → merged
            → VERIFY(/retirement) → advice(plan_retirement_income)
SPOUSE      (SKIP if unmarried) campaign_spouse_work → BOTH modes → campaign2_spouse_pensions
              "**Does your spouse have pensions of their own?**" → VERIFY(/income) → SILENT
EXPEND      base_expenditure (SKIP if set) → VERIFY(/expenditure)
SYNTHESIS   "Here's your pension picture, built from what you told me…" (+ degrade line)
TERMINAL    campaign2_terminal "We've built your pension picture, {name}."
            [Take me to my retirement plan → /retirement]
            completed=true, completed_at=now, points, level_up?; active_campaign cleared
```

### 12.2 Existing user — re-entry delta walk (E2E-verified: 7 gap questions, zero re-asks)

```
"Already with Fynla? Log in" → /login?redirect=/dashboard?openFyn=journey&from=pensioncheck
  → login+MFA → onboarding/start (from=pensioncheck)
  ├ bare start / from=savetax → 409 already_completed
  ├ mid-campaign (step set) → resume SSE → [Continue | Something else]
  └ fresh re-entry → step=campaign2_existing_recap, active_campaign='pensioncheck'
       (onboarding_completed stays TRUE throughout)

campaign2_existing_recap
  "Welcome back, {name}. Let's take a proper look at your pension.
   Here's what I already have from you: [income / DC pensions / DB pensions / spouse]
   **Is that all still right?**"        [Yes, that's right | Something's changed]
  ├ "Something's changed" → campaign_verify_edit (⚠ AUDIT FLAG P2: after one landed
  │    edit + inline confirm the walk exits to SYNTHESIS → TERMINAL, skipping the gaps)
  └ "Yes, that's right" → first non-skipped section:

  income        SKIPPED (income captured in savetax)
  pensions      campaign_dob SKIPPED (DOB known)
                campaign_occupational_scheme SKIPPED if valued workplace DC on file, else asked
     Q1..Qn     campaign2_pension_pots — pot backfill per unvalued DC pension (savetax never
                captured pots, so this usually fires; loops per scheme)
     Q          campaign_pension_contribs — personal/SIPP (update-vs-create by id)
     Q          campaign2_pension_db — final salary schemes
     Q          campaign_pension_history — ONLY if income > higher-rate threshold
     Q          campaign2_flexible_access — ONLY if age ≥ 55 and not already flagged
                → VERIFY(/retirement) → pensions advice
  state_pension Q  campaign2_state_pension — unless a row exists ("not sure" advances)
  goals         Q  campaign2_retirement_goals — unless both targets set
  spouse        Q  campaign2_spouse_pensions — unless unmarried (spouse_work skipped, mode known)
  expenditure   SKIPPED (captured in savetax)

  → synthesis ("Here's your pension picture…") → campaign2_terminal
     [Take me to my retirement plan → /retirement]
     active_campaign=null; completed_at UNTOUCHED; no duplicate awards (D3-verified)
  → afterwards: advice mode answers normally (D2-verified)

ANY POINT: "Something else" → paused_at_step parked, step=null, active_campaign=null
   → next message routes to Advice Fyn; re-entering later = fresh recap
   (⚠ a bare /onboarding/start after pause 409s — re-entry NEEDS from=pensioncheck)
```

---

## 13. Eventuality map

The shared machinery (interruption/resume, ambiguous answers, verify-edit honesty + read-back, off-script questions, WP-1/F5 capture integrity, queue/lock, gates) is identical to SaveTax — see `saveTax.md` §12. Pensioncheck-specific eventualities and differences:

| # | Eventuality | Behaviour |
|---|---|---|
| 1 | **Existing user interrupted mid-re-entry** | Everything persists (step, `active_campaign`, transcript). Re-hitting the campaign entry (`from=pensioncheck`) passes the 409 gate → **resume branch** → existing conversation continues at the saved step. |
| 2 | **Existing user pauses mid-re-entry ("Something else")** | Step nulled AND `active_campaign` nulled → advice mode. **A later bare `/onboarding/start` (no `from`) hits the 409** — the walk is only re-enterable via `from=pensioncheck`, which restarts at the recap (data preserved, position lost). |
| 3 | **"Not sure" answers** | First-class: pot loop escapes on don't-know tokens; State Pension schema forbids the tool call entirely ("never pass 0") and `advance_on_answered_question` moves on; the gap surfaces later as engine advice (no-forecast recommendation). |
| 4 | **Model hallucinates a capture** | `capture_state_pension` write-guard (positive forecast/NI-years only; the NHS-age incident); F5 honest ack — no "Recorded" without a landed write; duplicate `create_pension` blocked → contribution turns update by `entity_id`. |
| 5 | **Answers differently at the recap** ("Something's changed") | `campaign_verify_edit` with generic context (no `verify_section`) → honesty gate + read-back work, **but** the post-edit confirm routes to synthesis→terminal (FLAG P2). |
| 6 | **User asks an off-script question mid-walk** | Director-internal A1 answer-first (same as savetax); question-only turns never advance; `advance_on_answered_question` states advance when an answer rode along. |
| 7 | **Completes, then re-enters again (3rd, 4th time)** | Identical re-entry each time; `completed_at` byte-identical; per-state points deduped (`onboarding:{stateId}`) so repeat walks award nothing new; terminal award count stays 1 (D3). |
| 8 | **Fresh user with `from=pensioncheck`** | Gets `entry` (`base_work`), NOT `reentry_entry` — plus a harmless `active_campaign` stamp (cleared at every exit). Funnel-fallback arrivals (lost `from=`) get the campaign but no stamp — also fine, the `onboarding_completed===false` leg routes them. |
| 9 | **Phone arrives from an ad link to /pensioncheck** | ⚠ FLAG P1 — `CAMPAIGN_PREFIXES` omits `pensioncheck`: the phone is redirected to plain `/m` (funnel path + utm lost); `/m?to=/pensioncheck` is rejected by `isFramableTo`. The user can still tap the homepage card inside the frame. |
| 10 | **Completed user on /m wanting to re-enter** | ⚠ FLAG P3 — no /m entry point exists: `onboardingActive` requires `onboarding_completed===false`, so /m never fires `startOnboarding(from)` for a completed user, and the verify pills won't render for a re-entry walk started elsewhere. Server dispatch is correct (typed messages route to the director); initiation is desktop-only today. |
| 11 | **Abandons the funnel / verification** | Same as savetax: nothing server-side pre-register; `PendingRegistration` 24h with funnel_answers preservation; hourly cleanup. |
| 12 | **Estimate service fails** | Homepage card renders without the figure; plan page falls back to static defaults (£140,000/67); registration unaffected. |

---

## 14. Audit — what's in place, flags, deferred

### In place and verified (live E2E on csjones, real xAI model, 2026-07-04)

- **D1 fresh walk GREEN**: homepage CTA → funnel → £282,751 personalised estimate → registration → full walk → 4-bullet synthesis → terminal → `/retirement` rendering all captures. DB-verified rows (dc/db/retirement_profiles, funnel keys, no garbage State Pension row from "not sure", 16 point awards).
- **D2 delta walk GREEN**: julycsj3 re-entry → recap → exactly 7 gap questions, zero re-asks → synthesis → terminal → advice mode normal afterwards.
- **D3 integrity GREEN**: `completed_at` byte-identical through two re-entries; terminal award count 1; `active_campaign` cleared; 4 milestones.
- **D4 savetax regression GREEN**: zero campaign bleed; June behaviours intact.
- **D5 contribution fix GREEN**: "£200 a month" → `monthly_contribution_amount=200.00` on the existing pension, truthful ack, no duplicate.
- Full test pinning: `CampaignReentry{Start,Dispatch,Exit}Test`, `OnboardingStartCampaignMapTest`, `Pensioncheck*Test` unit suites, `ExistingRecapBuilderTest`, `PensioncheckSynthesisTurnTest`, `CaptureWriteFailureTest`, `PensioncheckRoutesTest`. Suite at merge: 5,490 passed / 30 expected skips.

### AUDIT FLAGS — resolution status (fixes shipped in PR #612, MERGED to dev `9c9e7d2` 2026-07-06; suite 5,506 green; tests in `tests/Feature/AI/CampaignAuditFixesTest.php`)

**Live-browser-verified on csjones 2026-07-06 (Playwright, real xAI):** P1 (phone 302 → `/m?to=/pensioncheck` + funnel renders framed), P2 (recap-edit → DB read-back £26,000 → confirm → gap walk continued at the workplace-pension question, NOT terminal), P3 (`/m` `?from=pensioncheck` re-entry initiation + verify pills mid-walk), P4 (pause → resume at the parked step, pointer consumed), P6 (history-on-file skip fired despite higher-rate income), S5 (locked conversation → honest "Fyn is still answering…" line, message queued not lost), S6 (utm→`signup_source='facebook'` on the users row via a full funnel registration). Post-terminal integrity re-verified: `completed_at` byte-identical, no garbage State Pension row from "not sure", terminal award count 1. **Three live-found follow-ups fixed inside #612:** `UserResource` now exposes `active_campaign` (the explicit field list omitted it — the /m gate couldn't see it); a bare `/start` for a MID-walk completed re-entrant now reaches the resume branch instead of 409ing (the /m dashboard re-probes on reload); the /m store mirrors the re-entry stamp client-side (login-time `store.user` was stale for the whole in-session walk).

1. **[FIXED — PR #612] P1 — Phone ad deep-links** — `pensioncheck` added to `RedirectPhoneToMobile::CAMPAIGN_PREFIXES`; `/pensioncheck` (+`/plan`, +utm) now survives the phone→/m redirect and passes `isFramableTo`. `MobileScaffoldTest` covers it.
2. **[FIXED — PR #612] P2 — Recap-edit short-circuit** — "Something's changed" now stamps `verify_section='recap'`; the Director gains recap-aware focus (`pensioncheck` catalogue), a combined income+pensions+spouse record context, and the pensions snapshot for read-backs; the post-edit confirm re-enters the **first non-skipped section** (new `OnboardingStateMachine::firstCampaignSection`) instead of synthesis→terminal.
3. **[FIXED — PR #612] P3 — /m re-entry** — `onboardingActive` now counts `active_campaign` (pills + dock-resume work mid-re-entry); the /m dashboard fires `startOnboarding(from)` for completed users arriving with a campaign token (server decides re-entry vs 409); `onboarding_complete` mirrors the `active_campaign` clear. Needs the csjones browser pass (no JS unit harness).
4. **[FIXED — PR #612] P4 — Pause-then-bare-start dead-end** — `startOnboarding` resolves the paused campaign from the preserved path/selection/`paused_at_step`, bypasses the 409 for reentry-enabled campaigns, resumes at the parked step, re-stamps `active_campaign`, and reuses the existing conversation.
5. **[FIXED — PR #612] P5 — `restart` leaves `active_campaign`** — the restart action clears it; completed re-entrants get a full column reset (step nulled, routed to advice) instead of parking at `path_choice`.
6. **[FIXED — PR #612] P6 — pension-history data-presence skip** — `skipIfPensionHistoryNotApplicable` now skips when `PensionInputHistory` rows exist, ahead of the higher-rate gate.
7. **[FIXED — PR #612] P7 — flexible-access record context** — `campaign2_flexible_access` carries `record_context='pensions'` so the yes-branch `update_record` targets the row by `entity_id`.
8. **[FIXED — PR #612] P8 — inline-capture whitelist** — `captureToolSet` includes `capture_retirement_goals` + `capture_state_pension`, so post-campaign goal/State-Pension statements in advice mode delegate to the real handlers.
9. **[KEPT — by design] P9** — `funnel_answers.age`/`.pot` remain stored (estimate-only inputs); harmless, noted for a future data-minimisation review.
10. **[FIXED — PR #612] P10 — campaign free string** — `RegisterRequest::prepareForValidation` strips a `funnel_answers.campaign` not in the `campaign_map` keys (stripped, never 422 — a stale funnel client must not block registration).

### Known / deferred (patch-notes §Known items — CSJ-owned decisions)

1. **All campaign copy is DRAFT** (funnel, plan page, homepage card, Fyn walk lines); **OG images `og/pensioncheck*.jpg` referenced but not created.**
2. **Carry-forward re-inclusion needs conscious blessing** — restored pensioncheck-only (higher-rate-gated) despite the June #586 savetax removal.
3. **Post-terminal affinity reverts to tax-first for re-entrants** (terminal clears the selection; their funnel stamp says savetax) — needs a durable signal if retirement-first should persist.
4. **Pension access age 55 hardcoded ×2** (rises to 57 April 2028) — wants TaxConfigService effective-from.
5. Cosmetics: web verify-bubbles render literal `**` (pre-existing, shared with savetax); retirement page derives monthly contribution from salary → %-only pensions display £0.
6. Deferred by design: no `/retirement-plan` composed landing page (D4 — the composed retirement plan surfaces through the actions surfaces); desktop achievements/milestones parity; milestone email loop; fees/beneficiary/member-number questions (Tier-4 fields whose advice value doesn't justify a chat question in v1).

---

## 15. File inventory (pensioncheck-specific; shared files in `saveTax.md` §14)

| Path | Role |
|---|---|
| `public/pages/index.php:30-45, 270-277` | Homepage `feature-pensioncheck` card + representative pot |
| `public/pages/pensioncheck.php` + `public/pages/js/pensioncheck.js` | Funnel — six questions, campaign stamp, localStorage + query handoff |
| `public/pages/pensioncheck-plan.php` + `public/pages/js/pensioncheck-plan.js` | Plan page — estimate injection, register card, login re-entry link |
| `app/Services/Marketing/PensionEstimateService.php` | Banded projected-pot estimate (0.08 auto-enrolment, 2.5% real growth, TaxConfig thresholds + taper note) |
| `routes/web.php:617-642` | Funnel routes (before catch-all, `redirect.authed`) |
| `app/Http/Middleware/RedirectPhoneToMobile.php` | `CAMPAIGN_PREFIXES` (⚠ pensioncheck missing), `isFramableTo` |
| `database/migrations/2026_07_03_000001_add_active_campaign_to_users.php` | `users.active_campaign` |
| `config/onboarding.php:75-82` | `campaign_map` — pensioncheck `reentry:true`, `reentry_entry:'campaign2_existing_recap'` |
| `app/Http/Controllers/Api/AiChatController.php` | `routesToOnboardingDirector` (3-seam predicate), `startOnboarding` (conditional 409, stamping, resume) |
| `app/Services/Onboarding/OnboardingStateMachine.php` | `CAMPAIGN_SECTION_ORDERS/campaignSections/campaignVerifyConfig('pensioncheck')`, all `campaign2_*` states, data-presence skips, `buildExistingRecapPrompt`/`nextFromExistingRecap`, `buildPensioncheckFunnelRecapPrompt`, `nextFromCampaignDob` override, higher-rate + flexible-access + pot-fill skips |
| `app/Services/Onboarding/OnboardingChatDirector.php` | `PENSIONCHECK_SECTION_STRATEGY_TYPES`, `buildRetirementSectionAdvice`, campaign-aware `buildSynthesisAdvice` + degrade, `handleSomethingElseAction` active_campaign clear, `emitTerminalNavigationTurn` guards, contribution record-context appendix |
| `app/Services/Onboarding/OnboardingPromptBuilder.php` | `toolsForFocus('pensioncheck')` arm (the #610 root-cause fix) |
| `app/Agents/CoordinatingAgent.php` | `handleCaptureRetirementGoals` (+parking helpers, users sync, cache bust), `handleCaptureStatePension` (write-guard), `handleUpdateRecord` (aliases + allowlist + PensionStore routing) |
| `fyn-memory/procedural/tool_schema/campaign/capture_retirement_goals.xai.md`, `capture_state_pension.xai.md` (+`.md` twins, `capture_pension_history.*`) | LIVE tool schemas — descriptions govern model behaviour |
| `app/Constants/UpdateRecordAllowlist.php` | dc_pension allowlist incl. `has_flexibly_accessed`, `annual_salary`, `salary_sacrifice` |
| `app/Services/Stores/PensionStore.php` | `hasWorkplaceDcPensionWithValue`, `first/hasDcPensionsMissingPotValue`, `personalDcPensionsFor`, `dbPensionsFor`, `hasFlexiblyAccessedDcPension`, `captureInputHistory` |
| `app/Models/{StatePension,RetirementProfile}.php` | Write targets |
| `app/Services/Cache/CacheInvalidationService.php` | `retirement_analysis_{id}` etc. cache bust |
| `app/Services/Coordination/ComposedModulePlanService.php` + `PlanSources/RetirementStrategySource.php` | Composed retirement plan behind advice + synthesis |
| `app/Services/Mobile/NextActionsService.php` | `CAMPAIGN_AFFINITY['pensioncheck']='retirement'`, `applyCampaignAffinity` |
| `resources/js/router/index.js:874` | `/retirement → /net-worth/retirement` redirect (terminal CTA) |
| `resources/js/views/Login.vue` | `?redirect=` capture (re-entry path) |
| `resources/mobile/mixins/onboardingChat.js` | `from` forwarding (PR #607) |
| Tests: `tests/Feature/AI/CampaignReentry{Start,Dispatch,Exit}Test.php`, `OnboardingStartCampaignMapTest.php`, `tests/Unit/Services/Onboarding/Pensioncheck*`, `ExistingRecapBuilderTest`, `GroupedExtractAdvanceOnAnsweredTest`, `tests/Feature/Onboarding/PensioncheckSynthesisTurnTest`, `CaptureWriteFailureTest`, `tests/Feature/PublicPages/PensioncheckRoutesTest` | Pins for every load-bearing behaviour above |

**Standing test user:** `julycsj3@example.com` (id 168, `Password1!`) on csjones — completed SaveTax + pensioncheck data (Personal Pension £25,000 + £200/month, goals 60/£35,000).
