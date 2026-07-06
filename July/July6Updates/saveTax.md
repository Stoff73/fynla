# SaveTax Campaign — Full Map & Audit

*2026-07-06. The complete current-state map and audit of the SaveTax campaign: pre-registration funnel, registration, pull-through, the Fyn question decision tree, and the eventuality map. Grounded file:line against the dev tip (`6f965f1`, post-#607–#610) via the `fynla-fixes` worktree — line numbers are indicative (the worktree carries import-order churn); `path:method` anchors are primary. Companion docs: `July/July3Updates/campaign-playbook.md` (F1–F15 formatting standard), `campaign-blueprint.md` (seams), `savetax-recs-gamification-map.md` (actions/gamification), and `July/July6Updates/pensionCampaign.md` (campaign #2).*

---

## 1. The end-to-end flow (one page)

```
Ad / CTA / homepage feature-savetax card
  → FUNNEL  /savetax  (public/pages/savetax.php + js/savetax.js)
      5 questions: employment · income band · spouse? · spouse income (conditional) · assets (multi)
      answers → localStorage['savetax_answers'] (campaign:'savetax' baked in) + query params
  → PLAN PAGE  /savetax/plan  (savetax-plan.php + js/savetax-plan-v4.js)
      SaveTaxEstimateService::estimate() server-side (all tax values via TaxConfigService)
      → window.SAVETAX_ESTIMATE (hex-flagged JSON) → personalised £ figure + allowances grid
      inline register card → POST /api/auth/register (+funnel_answers)
  → PENDING REGISTRATION  (PendingRegistration, 24h expiry, funnel_answers survives re-registration)
  → VERIFY CODE  → user created: funnel_answers copied, FunnelAnswersMapper pre-fills profile
  → /register?from=savetax (funnelHandoff hold panel, verify modal only)
  → dashboard?openFyn=journey&from=savetax   (phones: /m iframe; from= lost by design,
      recovered server-side from funnel_answers.campaign)
  → POST /api/ai-chat/onboarding/start → campaign_map match → entry state base_work
  → THE WALK  income → savings → investments → pensions → spouse → expenditure
      per section: capture → ONE gate → verify announce → navigate → Continue/Edit pills
      → confirm → section advice ("added to your actions list") → next section
  → SYNTHESIS  buildSynthesisAdvice mirrors composed_plan.items (byte-consistent with /tax-strategy)
  → TERMINAL  "We've created your personal tax strategy, {first_name}."
      [Take me to my tax strategy → /tax-strategy]  onboarding_completed=true
  → POST-CAMPAIGN  applyCampaignAffinity: tax actions first on /m dashboard; milestones + feed
```

One backend for web AND `/m` (`AiChatController::sendMessage` / `onboarding/start`) — surface-agnostic by construction (Rule 19 for free). **SaveTax is a one-shot campaign: `reentry => false` — a completed user can never re-enter the walk** (contrast pensioncheck).

---

## 2. Pre-registration — the funnel (`/savetax`)

**Files:** `public/pages/savetax.php` (live page) + `public/pages/js/savetax.js` (`?v=7`). A mockup variant `savetax-v2.php`/`savetax-v2.js` serves `/savetax/v2` (explicit Continue per step, "spouse or partner" wording) — **the live page is `savetax.php`**.

Minimal funnel chrome — sticky header, back button, "1 of 4" step label, progress bar. Not the site nav (`savetax.php:87`).

### 2.1 The questions (exact copy)

| # | Screen | Question | Sub-copy | Key | Options (`data-value` → label) |
|---|---|---|---|---|---|
| Q1 | `s-employment` | "What is your employment status?" | "We use this to identify the tax reliefs most relevant to you." | `employment` | `not-employed`, `part-time`, `full-time`, `self-employed`, `retired` |
| Q2 | `s-income` | "What is your annual income?" | "Your gross income before tax, including salary, self-employment, and pension income." | `income` | `upto_50270`, `50271_100000`, `100001_125140` (badge **"Tax-trap zone"**), `over_125140` |
| Q3 | `s-spouse` | "Do you have a spouse?" | "Couples may be able to transfer allowances and split income to reduce their overall tax bill." | `spouse` | `yes` / `no` |
| Q4 (only if Q3=yes) | `s-spouse-income` | "What is your spouse's annual income?" | "Their gross income before tax. This helps us identify allowance transfer opportunities." | `spouseIncome` | `zero` ("No income") + the four Q2 bands |
| Q5 (multi) | `s-assets` | "Which of these do you have?" | "Select all that apply. Each one may unlock additional tax saving opportunities." | `assets[]` | `bank`, `savings`, `pension`, `property`, `isa`, `investments` |

### 2.2 Behaviour (`savetax.js`)

- **Answers object** initialised as `{ campaign: 'savetax', employment: null, income: null, spouse: null, spouseIncome: null, assets: [] }` — **the campaign stamp is baked in from line 4**, not added at submit.
- `sequence()`: `employment → income → spouse` (+ `spouse-income` when spouse=yes) `→ assets` — 4 or 5 steps.
- Single-select screens **auto-advance 220ms** after selection (`selectSingle()`); only the assets screen shows the footer Continue, labelled **"See your tax insights"**. Zero assets is valid. Choosing spouse=no clears `spouseIncome`.
- `persistAndGoToPlan()`: writes the full object to **`localStorage['savetax_answers']`** (try/catch for private mode), then navigates to `(window.FYNLA_BASE||'') + '/savetax/plan?from=savetax&employment=…&income=…&spouse=…&spouseIncome=…&assets=a,b,c'` — answers travel **both** ways: localStorage (for registration) and query params (for the server-side estimate).

---

## 3. Pre-registration — the plan page (`/savetax/plan`)

**Files:** `public/pages/savetax-plan.php` + `public/pages/js/savetax-plan-v4.js` (`?v=12`). Older `-v2/-v3/-v4.php` + JS serve mockup routes only (`no-store`).

### 3.1 Server-side estimate

- PHP preamble (`savetax-plan.php:1-31`) reads `$_GET['income'|'spouse'|'spouseIncome'|'assets']` (assets split on comma, **capped at 12**). Direct-visit/SEO default when `income` is empty: `{income:'50271_100000', spouse:'no', assets:['savings','pension','isa']}`.
- `app(SaveTaxEstimateService::class)->estimate($answers)` in try/catch → `null` on any Throwable (page still renders).
- Injection: `window.SAVETAX_ESTIMATE = <?= json_encode($estimate, …HEX flags) ?>` (`savetax-plan.php:217`) — **the JS does no tax math, it only renders**.

### 3.2 `app/Services/Marketing/SaveTaxEstimateService.php`

- Single public method `estimate(array $answers): array`; constructor injects `TaxConfigService` — **every tax value via config (Rule 2)**.
- Band model: `BAND_INCOME` maps band → assumed **upper-bound** income (`upto_50270`→50,270 … `over_125140`→150,000); `TRAP_BAND = '100001_125140'`; unknown bands default `upto_50270`.
- Returns `{tax_year, assumed_income, marginal_rate, savings:[{key,label,amount,reason}], savings_total, allowances:{items,total}}`.
- Savings line items: pension relief (only when NO `pension` asset ticked; the trap band computes relief down to £100k as **`tax_trap_60` "60% Tax Trap"** — computed by the exact engine `incomeTax()`/`pensionRelief()` with PA taper + band extension, so the 60% rate emerges naturally); ISA (10% of income × marginal rate); PSA × rate (`savings|bank`); dividend allowance × dividend rate (`investments`); CGT allowance × CGT rate (`investments|property`); **spouse-transfer levers only when `spouse=yes` AND `spouseIncome='zero'`** — spouse PA, spouse PSA, spouse starting-rate, plus Marriage Allowance only when the user is basic-rate.
- **`isSpouse` flag** (PR #593): the spouse's Personal Allowance card **omits** the "Automatically used against your income." note (`personalAllowanceItem(..., isSpouse:true)`) — in the unregistered estimate we don't know the spouse's actual position.
- `allowances()`: capacity view — own PA (greyed unless non-earner/in-taper), ISA, Pension Annual Allowance (non-earner capped at the £3,600 relevant-earnings route), PSA, dividend, CGT; married adds Marriage Allowance + the spouse's full per-person set. `total` sums only `on === true` items.

### 3.3 Register card

- Inline form `#register-form` (`savetax-plan.php:117-141`): first/last name, email, password; button **"Register for free"**; note "Takes you straight to your dashboard with Fyn open, ready to guide your onboarding."
- `wireRegister()` (`savetax-plan-v4.js:284-372`): primes `GET /sanctum/csrf-cookie` → `POST /api/auth/register` with `{first_name, surname, email, password, password_confirmation, funnel_answers: realFunnelAnswers()}` — `realFunnelAnswers()` sends **the raw localStorage object (with campaign stamp), never the demo persona** (null for direct visitors).
- On `requires_verification`: stashes `sessionStorage['fynla_pending_verify'] = {pending_id, email}` → redirect `/register?from=savetax`. `account_deleted_restorable` → plain `/register`. Email exists → "That email already has an account — please sign in instead."

---

## 4. Routes & phone routing

- **Route block** `routes/web.php:644-717`, declared before `/m` (`:723-731`) and the SPA catch-all (`:752-754`; `{any}` constrained `.+` not `.*` so route caching can't shadow `/`). Live `/savetax` + `/savetax/plan` sit in a `Route::middleware('redirect.authed')` group — **logged-in users bounce to the dashboard** (funnel is guest-only). Served via `include public_path('pages/…')` with `Cache-Control: public, max-age=300, stale-while-revalidate=60`. Mockup routes (`/v2`–`/v4`) are `no-store`.
- **`RebasePublicPageUrls`** (global web middleware, `Kernel.php:92`): on subdirectory deploys (csjones `/fynla`) rewrites root-relative URLs and injects `window.FYNLA_BASE` — what both funnel JS files use. No-op on prod; skips SPA responses.
- **Phones**: `RedirectPhoneToMobile` — phone UA hitting `/savetax` → 302 `/m?to=%2Fsavetax` (`savetax` IS in `CAMPAIGN_PREFIXES`; query preserved for utm). Framed loads are exempt (`Sec-Fetch-Dest: iframe`) so the funnel runs inside the `/m` iframe without looping; `?full=1` cookie is the escape hatch. `mobile-host.blade.php` frames only `isFramableTo()`-validated paths (open-redirect guard).

---

## 5. Registration flow

**Route:** `POST /api/auth/register` (`routes/api.php:134`), `throttle:auth-5` (named per-path limiters — `"$path|$ip"` keys so the auth flow never 429s itself).

1. **`AuthController::register`** (`:64-137`): soft-deleted account → `account_deleted_restorable`; existing verified email → 422 `email_exists`; else `PendingRegistration::createOrUpdate([... funnel_answers, signup_source])` + 6-digit code email → 201 `{requires_verification, pending_id, email(masked)}`.
2. **`PendingRegistration`** (`app/Models/PendingRegistration.php`): 24h expiry (`EXPIRY_HOURS=24`); `funnel_answers` cast array; **`createOrUpdate` preserves `funnel_answers`/`signup_source` from the existing pending row when the new payload omits them** (`:89-90`) — an abandoned funnel verification followed by a plain SPA re-register on the same email keeps the SaveTax entry signal (/bug 6.1 fix). A fresh payload WITH funnel_answers wins (present-and-new beats preserved).
3. **Validation** (`RegisterRequest::rules`): names required, password `min:8|confirmed`+complexity; `signup_source` allowlisted (linkedin/facebook/instagram/tiktok/x/youtube); `funnel_answers` nullable array — `campaign|employment|income|spouseIncome` string max:40, `spouse` max:10, `assets.*` max:40 (loose boundary validation by design).
4. **Verification** (`AuthController::verifyCode` `:478-610`): expired pending → "register again"; **5 wrong attempts → pending deleted**; on match creates the user copying `first_name, middle_name, surname, email, password, role_id(ROLE_USER), referred_by_code, signup_source, funnel_answers` (`:533-543`). `plan`/`billing_cycle` NOT copied — freemium sets `tier='free'`. Then:
   - **`FunnelAnswersMapper::mapToProfile`** (`:551`) — never overwrites: `employment` → `users.employment_status` (`full-time`→`full_time`, `not-employed`→`unemployed`, …); `spouse` → `marital_status` (married/single); `spouseIncome` → `household_calculation_mode` (`zero`→`single_earner_couple` + `marriage_allowance_eligible=true`, band→`dual_earner`). **Income band deliberately NOT written** (a range is not a figure — Fyn confirms the real number in the walk).
   - Consents recorded incl. `TYPE_AI_CHAT` (consent at registration — no toggle), referral linking, audit log, pending deleted, Sanctum token issued, `PointsService::recordLogin`.
5. **Cleanup:** `registrations:cleanup` scheduled hourly deletes expired pendings — an abandoner's funnel answers survive at most ~24h unless verified.

---

## 6. Post-registration handoff (what is pulled through)

**Desktop:** `Register.vue::onMounted` pops `fynla_pending_verify` → `funnelHandoff = true` (quiet "setup panel" replaces the form — no form flash, #594) → `VerificationCodeModal` directly (user only types the code). `completeRegistration()`: token stored, user fetched, aiChat reset, then **any `from=<id>` → `Dashboard` with `{openFyn:'journey', newUser:'1', from}`**. `Dashboard.vue` (`:1396+`): `openFyn==='journey'` captures `from` before stripping the query → `aiChat/startOnboardingConversation({from})` → `POST /api/ai-chat/onboarding/start`.

**Phones (/m):** registration happens in-frame; after verification the framed SPA's router guard (`router/index.js:1540-1564`) copies the token to `localStorage['m_scaffold_token']` and `window.location.replace('/m/app')` — **`from=savetax` is lost here by design**. `/m` `Dashboard.vue::mounted` auto-opens Fyn when `onboarding_completed === false`; the mixin `startOnboarding(from)` posts `{from}` when truthy, else `{}`. **The server closes the gap**: `startOnboarding`'s funnel fallback (`AiChatController:693-704`) keys the campaign off the durable `users.funnel_answers['campaign']` (string-guarded; legacy rows default `'savetax'`).

### 6.1 `funnel_answers` key → consumer map

| Key | Consumers |
|---|---|
| `campaign` | `startOnboarding` funnel fallback (`:699-704`); `NextActionsService::applyCampaignAffinity` (campaign→module affinity; non-stamped legacy funnel → savetax→tax); `PendingRegistration::createOrUpdate` preservation |
| `employment` | `FunnelAnswersMapper` → `employment_status`; `buildFunnelRecapPrompt` recap bullet |
| `income` | recap bullet; **income cross-check** — `detectIncomeFunnelMismatch` challenges a captured figure outside the funnel band (`FunnelIncomeBand`) |
| `spouse` | mapper → `marital_status`; recap bullet |
| `spouseIncome` | mapper → `household_calculation_mode` + `marriage_allowance_eligible` (what lets `campaign_spouse_work` skip itself); spouse-band mismatch check; recap suffix |
| `assets` | ALL the walk's skip predicates: `skipSectionIfNoCash`, `skipSectionIfNoInvestments`, `skipIfNoIsa`, `skipIfNoBankOrSavings`, `nextFromCampaignDob` pension gate, `buildCampaignBankAccountsPrompt`/`buildCampaignDobPrompt` copy, `sectionLabel`; recap bullets + F12 time estimate (3 min + 1/extra asset) |
| whole array (non-empty) | funnel-recap first turn gates (`buildWorkPrompt`/`buildPersonalPrompt`); `MilestoneDetectionService::upcoming`/`detectJourney` |

**Guards:** registration path fully excluded from `PreviewWriteInterceptor` (`api/auth/register`, `verify-code`, `resend-code`, `api/ai-chat/onboarding`); throttles `auth-5`/`auth-10`; 5-wrong-codes deletion; plan-page `esc()` HTML-escaping; `isFramableTo` open-redirect guard.

---

## 7. The Fyn walk — entry

`POST /api/ai-chat/onboarding/start` → `AiChatController::startOnboarding` (`:576`). **Gate order:** consent 403 → (re-entry resolution — savetax `reentry:false` so completed users always **409 `already_completed`**) → flag off 503 → preview 403 → mid-flow resume SSE (`onboarding_fyn_step !== null` → `{type:'resume', conversation_id, current_step}`) → fresh start.

Fresh start: `AiConversation` created (`metadata.source='fyn_onboarding'`); `from` matched against `campaign_map` FIRST, then `journey_map`, then the funnel fallback; match → `onboarding_fyn_path='campaign'`, `onboarding_fyn_selection='savetax'`, `onboarding_fyn_step='base_work'` (`config/onboarding.php:80`), first turn streamed via `emitFirstTurn`.

**The greeting** (`buildFunnelRecapPrompt`, fires once per conversation via `stateTurnAlreadyDelivered`):

> Hi {firstName}, I'm Fyn — thanks for those answers. Here's what you've told me:
> - *(bullets: employment / income band / spouse (+earning band) / assets list)*
>
> I've started your profile from what you told us, and to build your personalised tax plan I just need a few more details — this usually takes about {3 + max(0, assets−1)} minutes.
> *(BUBBLE_BREAK `\x1E` — splits into a fresh bubble)*
> **Let's start with your income.** Tell me your gross annual income (this includes bonuses and commissions).

**Dispatch for every subsequent message** — `routesToOnboardingDirector()` (`AiChatController:898-903`), shared by all three seams (`sendMessage`, `streamQueuedMessage`, `action`):
```php
(onboarding_completed === false || active_campaign !== null)
  && onboarding_fyn_step !== null
  && config('onboarding.fyn_flow_enabled')
```

**Prompt lockstep (F15):** every state's DATA (prompt_text/retry_text/bubbles) lives in the corpus `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` AND `OnboardingStateMachine::inCodeStates()`; the merge (`transitionTable`) takes corpus DATA + code callables; `OnboardingWorkflowTableGoldenMasterTest` enforces parity. Tool behaviour is governed by the live `.xai.md` schema descriptions.

---

## 8. The Fyn walk — section machinery

**Order** — `CAMPAIGN_SECTION_ORDERS['savetax']` (`OnboardingStateMachine:178`): `income → savings → investments → pensions → spouse → expenditure`.

**Section map** — `campaignSections('savetax')`:

| Section | Entry state | Whole-section skip |
|---|---|---|
| income | `base_employment` | none |
| savings | `campaign_isa_holdings` | `skipSectionIfNoCash` — funnel assets has none of savings/bank/isa |
| investments | `campaign_investment_accounts` | `skipSectionIfNoInvestments` — no funnel `investments` AND no S&S-ISA row created earlier |
| pensions | `campaign_dob` | none (DOB always wanted) |
| spouse | `campaign_spouse_work` | `skipIfNotMarried` |
| expenditure | `base_expenditure` | none |

`nextCampaignSection(after, user)` walks past the finished section, applies section skips, then per-state `skip_if` **transitively** (`applySkipRules`, bounded depth 20). Sections exhausted → `campaign_synthesis`.

**Verify config** — `campaignVerifyConfig('savetax')`: income→`/income`, savings→`/savings`, investments→`/investment`, pensions→`/retirement`, spouse→`/income`, expenditure→`/expenditure`. The `/m` allow-list mirrors it (`onboardingChat.js:26`).

**Tools per turn:** grouped-extract states arm exactly ONE extraction tool; delegated states arm `OnboardingPromptBuilder::toolsForFocus('savetax')` = `create_pension, capture_salary_sacrifice, capture_pension_history, capture_charitable_giving, create_savings_account, create_investment_account, create_holding, capture_spouse_work_status, capture_spouse_household_data, capture_spouse_non_working_assets` + `update_profile`, `update_record`.

---

## 9. The Fyn walk — full state table

### Income

**`base_work`** (grouped_extract, `capture_work_details`) — greeting/funnel recap on turn 1, else "Brilliant. What's your gross annual income? This includes bonuses and commissions." (self-employed/part-time variants). Retry: "I just need your gross annual income in GBP — could you share that?" Writes `users.employer/occupation/annual_employment_income` (or `annual_self_employment_income`). **Funnel-band cross-check**: figure outside the funnel band → "Earlier you told us your income was {band}, but you've entered £{X}. That changes your tax-saving calculation — is £{X} right?" [Continue|Change]. → `base_employment_more`.

**`base_employment_more`** (bubbles) — "Do you have any other roles or sources of earned income to add?" [Yes, add another | No, that's everything]. **This is the income section's ONE gate.** Yes → `base_employment` loop; No → `enterCampaignVerify('income')`.

**`base_employment`** (bubbles; skipped when `employment_status` set — i.e. every funnel user) — "And what's your employment situation at the moment?" Employed/part-time/self-employed → `base_work`; retired → `base_retirement_date` ("When did you retire? A year is fine…") → `base_expenditure`; not working → `base_expenditure`.

### Savings

**`campaign_isa_holdings`** (delegated; skip if no funnel `isa`) — "Let's look at your ISAs. **Do you have a Cash ISA or Stocks & Shares ISA? If so, what's the current balance and how much have you put in this tax year?**" Writes `savings_accounts` (cash ISA) / `investment_accounts`+holdings (S&S ISA); deterministic gap-fill via `AssetCaptureEntityExtractor`. → `campaign_bank_accounts`.

**`campaign_bank_accounts`** (delegated; skip if no funnel bank/savings) — copy adapts to holdings: "Now your savings — bank accounts and savings accounts. **For each, what's the balance and the interest rate?**" (bank-only / savings-only variants). → `enterCampaignVerify('savings')`.

### Investments

**`campaign_investment_accounts`** (delegated) — "Any investment accounts — General Investment Accounts, share trading platforms? If so, current value, your purchase cost, and any annual dividend income." → `enterCampaignVerify('investments')`.

### Pensions

**`campaign_dob`** (grouped_extract, `capture_personal_details`; skip if DOB set) — funnel has `pension`: "Now let's look at pensions and retirement — for that **I need your date of birth.** Something like 12 January 1985 or 12/01/85."; else the neutral variant. UK day-first parsing, 2-digit-year century inferred by the 18–105 age window; **short-format confirm-back**: "Your date of birth is **19th February 1982** — is that correct?" [Yes|No, change it] (No → DOB nulled + re-ask). → funnel has `pension` → `campaign_occupational_scheme`; else skip pension questions (keep DOB) → next section.

**`campaign_occupational_scheme`** (delegated; skip if not full/part-time employed; `advance_on_answered_question`) — "Tell me about your workplace pension. **What percentage of your salary do you contribute, does your employer match it, and is it via salary sacrifice?** If you don't have a workplace pension, just say so and we'll move on." Writes `dc_pensions` (`create_pension` + `capture_salary_sacrifice`). → `campaign_pension_contribs`.

**`campaign_pension_contribs`** (delegated; `advance_on_answered_question`; `record_context_mode='contribution'`) — "Beyond the workplace pension we covered, **do you make any personal pension or Self-Invested Personal Pension contributions? If so, how much per year (gross)?**" When a personal/SIPP/stakeholder pension already exists, the record-context appendix steers `update_record` (`monthly_contribution_amount`) instead of a duplicate `create_pension`; workplace-only users create the new SIPP. → `enterCampaignVerify('pensions')`.

*(Note: `campaign_pension_history` — 3-year carry-forward — was REMOVED from savetax in June (#586) and now sits only on the pensioncheck walk. `capture_pension_history` remains in the savetax tool arm.)*

### Spouse

**`campaign_spouse_work`** (bubbles + `bubble_capture`; skip if not married OR mode pre-set from funnel) — "Does your spouse work?" [Yes, they work | No, they don't currently work]. Tap synchronously dispatches `capture_spouse_work_status` → `household_calculation_mode` + `marriage_allowance_eligible`.
- dual_earner → **`campaign_spouse_household`** (grouped_extract) — "Great. **How much does your spouse earn annually, and do they have ISAs, investments, or pension contributions of their own?**" → `tax_strategy_household_inputs` row (`capture_spouse_household_data`).
- single_earner_couple → **`campaign_spouse_non_working_assets`** (grouped_extract) — "Got it — your spouse doesn't currently earn an income. That's actually useful for your tax strategy, because they have around £40,000 of unused tax allowances we can put to work. **Do they have any savings, ISAs, or investment accounts in their own name today, or is it all in yours?**" → household inputs (`capture_spouse_non_working_assets`).
- Both → `enterCampaignVerify('spouse')`.

### Expenditure

**`base_expenditure`** (free_text; skip if expenditure set) — "And roughly how much goes out each month — rent or mortgage, bills, food, transport, the lot? A ballpark figure is fine. I'll use it to work out your savings capacity, emergency fund target, and how much income you'll need in retirement." Parser retry: "…Try something like '£2,500' or '2.5k'." Writes `users.monthly_expenditure` + `expenditure_profiles` mirror. → `enterCampaignVerify('expenditure')`.

### The verify sub-flow (generic; section in `onboarding_fyn_context['verify_section']`)

1. **`campaign_verify_announce`** — "I've saved your {sectionLabel}. Next I'll take you to your {sectionLabel} page so you can check everything's correct — **tap Okay when you're ready.**" [Okay]. Navigation deliberately does NOT fire on this turn.
2. **`campaign_verify_navigate`** — navigation SSE `{route_path, description:'', section}` (description empty — the state-id leak fix) + "Here's your {sectionLabel} page — take a look and check everything's correct, then tell me: **does it look right?**" [Yes, that's right | No, change something]. On `/m` the dock closes, the screen shows the captured records, and **on-page Continue/Edit pills** (`MobileChrome.vue`) send the literal bubble answers — after awaiting a full transcript resume (never an empty box).
3. **Yes** → section advice (if any) → next section. **No** → **`campaign_verify_edit`** — "No problem — what needs changing?" Update-only delegated turn: record-reference appendix with entity ids; **honesty gate** (zero tool calls → "I wasn't able to apply that change. Could you tell me the specific value, for example \"change my Cash ISA balance to £25,000\"?" — stays on state); **deterministic read-back** from the DB ("Updated Cash ISA at HSBC — balance now £25,000.") → back to `campaign_verify_navigate`.
4. **`campaign_verify_more`** ("Anything else to add…?") is **ORPHANED** — `enterCampaignVerify` returns the announce state directly; each section's own gate already covered "more". Kept for golden-master parity only.

`sectionLabel` nuance: savings reads "savings and ISA accounts" / "ISA accounts" / "bank accounts" per funnel holdings.

---

## 10. Advice, synthesis, terminal

**Per-section advice** (`SECTION_STRATEGY_TYPES`, `OnboardingChatDirector:888-895`):

| Section | Composed-tax-plan item types voiced |
|---|---|
| income | `pa_taper_rescue`, `additional_rate_avoidance`, `tapered_annual_allowance` |
| savings | `isa_topup_vs_psa`, `joint_savings_psa_split`, `lifetime_isa` |
| investments | `bed_and_isa`, `dividend_allowance_harvest` |
| pensions | `salary_sacrifice_ni`, `pension_aa_carry_forward` |
| spouse | `non_earner_spouse_pension`, `savings_to_spouse`, `isa_topup_spouse`, `marriage_allowance_transfer`, `gia_to_spouse`, `gia_rebalance`, `isa_coordination` |

Text comes from the engine (`ComposedTaxPlanService::forUser` → `TaxStrategyCalculator`/`app/Services/Tax/Strategies/*`) — plan order, **max two items**, mechanical claims stated directly, judgement claims prefixed "You may want to consider: ", closing line **"I've added this to your actions list to come back to later."** Silent auto-advance when nothing matches. Spouse variant: "You can definitely save money with your spouse's allowances — around £{X} a year. We've added this to your actions list which we'll take you to shortly." (falls back to the £PA+ISA allowance figure from TaxConfigService when unquantified).

Advice states auto-advance (`emitAdviceTurn`); cycle defence: self-edge or chain > `MAX_ADVICE_CHAIN` (6) → force-complete + loud log (the PR #504 17,509-message incident); `campaign_synthesis.next` must be a callable string, never a closure.

**`campaign_synthesis`** (`buildSynthesisAdvice`): `$user->refresh()` FIRST, then mirrors `composed_plan.items` in composer order (PR #592 — byte-consistent with `/tax-strategy`):

> Here's your tax plan, built from what you told me — in the order I'd tackle it:
> - {title} — saves around £{X} a year
> Together these are worth roughly £{total} a year.
> For regulated advice personal to your circumstances, speak to a qualified financial adviser.

Graceful degrade (PR #610): empty/failed plan → "That's your details saved, {firstName}. You'll find your full tax strategy on the next screen." — never silent.

**`campaign_terminal`** — "We've created your personal tax strategy, {first_name}." + ONE route-carrying bubble **[Take me to my tax strategy → /tax-strategy]** (no auto-navigation; the tap navigates). Then `onboarding_complete` SSE → `done` → side effects: `active_campaign`+`onboarding_fyn_*` cleared unconditionally; `onboarding_completed=true` + `completed_at=now()` + `recordProgress` (points, dedup `onboarding:{stateId}`) **gated on not-already-completed**. The controller appends the `level_up` frame after `done` if a threshold was crossed.

**SSE contract (F2/F3):** one response = ack + `onboarding_advance` + next prompt + exactly ONE `done` (inner delegated `done`s swallowed); `BUBBLE_BREAK` splits bubbles; `quick_replies` persisted in metadata for transcript re-render; `onboarding_layout_change` opens non-advice turns; `capture_write_result` consumed internally (WP-1 honesty); per-conversation `fyn:inflight:{id}` lock (300s) → 202 queued → depth cap 3 → 429.

---

## 11. Decision tree (the whole walk)

```
POST /onboarding/start (from=savetax | funnel_answers.campaign fallback)
│ consent?─no→403 · completed?─yes→409 (one-shot) · flag off→503 · preview→403
│ step set → SSE resume{conversation_id, current_step}
└ fresh: step=base_work → funnel recap + "**Let's start with your income.**"

INCOME    base_work ─capture→ [band mismatch? challenge Continue/Change]
          → base_employment_more "other roles?"
             ├ Yes → base_employment → (employed→base_work loop | retired→base_retirement_date
             │        →base_expenditure ⚠early-exit | not working→base_expenditure ⚠)
             └ No  → VERIFY(income → /income) → advice(income) →
SAVINGS   [skip if no cash assets] campaign_isa_holdings [skip if no isa]
          → campaign_bank_accounts [skip if no bank/savings]
          → VERIFY(savings → /savings) → advice(savings) →
INVEST    [skip if no investments + no S&S ISA] campaign_investment_accounts
          → VERIFY(investments → /investment) → advice(investments) →
PENSIONS  campaign_dob [skip if DOB set] ─short-format?→ confirm-back Yes/No
          ├ funnel pension → campaign_occupational_scheme [skip if not employed]
          │   → campaign_pension_contribs (update-vs-create context)
          │   → VERIFY(pensions → /retirement) → advice(pensions) →
          └ no funnel pension → (DOB kept, pension Qs skipped) →
SPOUSE    [skip if not married] campaign_spouse_work [skip if mode known]
          ├ dual earner → campaign_spouse_household
          └ single earner → campaign_spouse_non_working_assets
          → VERIFY(spouse → /income) → advice(spouse, £-quantified) →
EXPEND    base_expenditure [skip if set] → VERIFY(expenditure → /expenditure) → (no advice)
          → sections exhausted →
SYNTHESIS campaign_synthesis (auto) — composed-plan bullets | fallback line
TERMINAL  campaign_terminal "We've created your personal tax strategy, {name}."
          [Take me to my tax strategy → /tax-strategy] → completed, cleared, points, level_up?

VERIFY(section) = announce "tap Okay" → navigate (SSE nav + pills)
   ├ "Yes, that's right" → advice → next section
   └ "No, change something" → verify_edit (honesty gate, DB read-back) → navigate again
```

---

## 12. Eventuality map (all user-journey branches)

| # | Eventuality | What happens (code path) |
|---|---|---|
| 1 | **User closes the browser mid-walk** | Everything persists — step advanced BEFORE each turn emits (`handleUserMessage:337`), transcript in `ai_messages`, captures written per-turn. Return (web): chat open → `/onboarding/status` → `resume` action → "Welcome back… Last time we were {step}" + [Continue \| Something else]; Continue re-emits the pending question. Return (/m): dashboard auto-opens Fyn, `resume` SSE, dock loads the **full transcript** (`loadTranscript`) — never an empty box. Mid-stream fatal error additionally flags an FR-M9 resumption prompt. |
| 2 | **User pauses ("Something else")** | `handleSomethingElseAction`: `paused_at_step` parked in context, `onboarding_fyn_step=null`, `active_campaign=null`; "Of course — what can I help you with?" → all subsequent messages route to read-only Advice Fyn. Resume = re-trigger `/onboarding/start` → **fresh-start path** (new conversation, back at `base_work`); already-captured data mitigates via skip_ifs — see AUDIT FLAGS. |
| 3 | **Ambiguous / wrong-format answer** | Bubbles: no match → "Sorry, I didn't catch that. Please pick one of the options above." + re-emit. Parsers: state-specific retry copy naming the format (F9). Two-digit-year DOB: century by the 18–105 window; ambiguity → confirm-back (F8). Grouped-extract no-capture: answer-the-question (A1) → single-figure clarification → `advance_on_answered_question` → retry_text. Partial capture → `details.missing` partial retry naming just the gap. **No max-retry counter — unbounded re-ask by design.** |
| 4 | **Answer contradicts the funnel** | `detectIncomeFunnelMismatch` (self + spouse bands): parks `pending_income_challenge`, "…is £X right?" [Continue → advance \| Change → re-ask \| new figure → re-capture]. |
| 5 | **User wants to change an answer** | In-section: verify "No, change something" → `campaign_verify_edit` — update-only, record ids in a reference appendix, `UpdateRecordAllowlist` field-gated, honesty gate (no write = no claimed change), deterministic DB read-back (F5) → re-confirm. Post-walk: "Edit details" (`buildEditPrompt`) via Advice Fyn → `delegate_to_capture`. |
| 6 | **Off-script question mid-walk ("what's an ISA?")** | Director-internal (never routes to Advice Fyn mid-walk): A1 answer-first — model's definitional answer passes `filterOffScriptContent(allowAnswer:true)` which still strips personal £ figures (statutory allowances carved out); a question-turn that captured nothing does NOT advance — the pending question re-asks. `advance_on_answered_question` states advance when an answer rode along with the question. |
| 7 | **User asks for a write in advice mode (post-walk)** | `AdviceFyn::WRITE_TOOLS` catalogue-strip; `WriteIntentClassifier` (deterministic tier) or `delegate_to_capture` (LLM tier) → `FynLoop::interceptHandoff` → `handleInlineCapture` — the `handoff` event never reaches the frontend (INV-2.4.1). Duplicates get a deterministic "already on file" ack. Out-of-remit topics get the canonical refusal. |
| 8 | **Capture failure / hallucination** | WP-1/F5 chain: `capture_write_result` events counted; model prose dropped when nothing landed ("Recorded!" can never reach the user falsely); all-failed → "I couldn't save that — {reason}…" / "I couldn't record anything new there…"; advance requires a LANDED write. Duplicate `create_pension` blocked → contribution turns steer `update_record`. Gap-fill synthesises dropped entities. Delegation throw → "I had trouble reading that. Could you try listing them one at a time?" |
| 9 | **Double-send / concurrent messages** | `fyn:inflight:{convId}` lock (300s TTL) → 202 `{status:'queued'}` (depth cap 3 → 429 "let Fyn answer those first"); web streams the queued turn on `done` — EXCEPT during onboarding (deliberately left queued so director bubbles aren't dropped through the advice consumer); cancel while queued OK, 409 once processing. /m blocks same-surface double-send via the `sending` flag. |
| 10 | **Gates on start** | 403 consent → 409 already_completed (savetax always, for completed users) → 503 flag off → 403 preview. Web silently falls back to a normal advice chat; /m falls to the generic greeting. |
| 11 | **User completes** | §10 terminal sequence: celebration + route bubble, `onboarding_complete`, `done`, completion writes + points (once-only), `level_up` frame after `done`, milestones minted on captures (not the terminal). Post-completion: dispatch → Advice Fyn; `applyCampaignAffinity` keeps tax actions first via `funnel_answers['campaign']`. |
| 12 | **Abandons at funnel** | Nothing persists server-side pre-register. localStorage survives on the device. |
| 13 | **Abandons at verification** | `PendingRegistration` holds `funnel_answers` 24h (hourly `registrations:cleanup`); re-register same email preserves them (new payload wins if present); 5 wrong codes → row deleted; verified email can never be overwritten by a pending. |
| 14 | **Arrives without `from=`** (/m iframe handoff) | Funnel fallback keys campaign off `funnel_answers['campaign']` (string-guarded, legacy default savetax). No funnel answers + no from → generic `path_choice` onboarding. |
| 15 | **Tries to re-enter after completing** | Always 409 — `reentry:false`. One-shot campaign. (The legacy `campaign_intro` "Nope" decline also permanently completes onboarding → walk unreachable; tax strategy accessible via the /tax-strategy page.) |

---

## 13. Audit — what's in place, flags, deferred

### In place and verified (live-browser E2E, D4 regression walk GREEN 2026-07-04)

- Full funnel → plan → register → walk → synthesis → terminal path on web and /m; June behaviours intact (no carry-forward question, #581 savings scoping, #582 current_account default).
- Canonical verify sequence with on-page Continue/Edit pills; announce gate; state-id leak fixed (was leaking on savetax too, PR #610).
- Synthesis mirrors `/tax-strategy` composed plan (PR #592 pin) + graceful degrade on empty plan (PR #610 — was a silent savetax bug).
- WP-1 capture integrity + F5 honest acks; income cross-check; DOB confirm-back; advice-chain recursion guard (#504).
- Gamification loop closed: per-state points (deduped), data-entry awards, level-up SSE, milestones on captures, campaign affinity (tax-first) on the /m dashboard.

### AUDIT FLAGS — resolution status (fixes shipped in PR #612, `campaign-audit-fixes` → dev, 2026-07-06; suite 5,506 green)

1. **[FIXED — PR #612] Retired / not-working early exit** — campaign-path users now continue the section walk: `nextFromEmployment` (not-working) and the new `nextFromRetirementDate` (retired) route to the income verify when income was captured, else straight to the next section, instead of exiting via `base_expenditure` and exhausting the section order. Corpus updated in lockstep.
2. **[FIXED — PR #612] Pause is restart-shaped** — `startOnboarding` now resumes a paused campaign at the parked `paused_at_step` (consuming the pointer) and reuses the existing onboarding conversation, for incomplete users and completed re-entrants alike.
3. **[FIXED — PR #612] `describeStep` campaign labels** — every campaign state now has a friendly resume-greeting label.
4. **[KEPT — by design]** Unbounded retries — a retry cap is a UX design call (CSJ's, if ever); the escapes remain valid answers, bubbles, or the pause.
5. **[FIXED — PR #612] `/m` 202-queued silent loss** — `apiStream` surfaces the queued 202 JSON; `send()` streams the queued reply with a 409 backoff and an honest give-up line.
6. **[FIXED — PR #612] Funnel `signup_source`** — funnel + plan pages capture `?utm_source=` (allowlisted, first-touch, same sessionStorage key as `sourceCapture.js`) and submit it with the register-card POST. Cache-busters bumped (`savetax.js?v=8`, `savetax-plan-v4.js?v=13`).
7. **[FIXED — PR #612] Wording drift** — `FunnelAnswersMapper`'s comment now reflects the live "Do you have a spouse?" wording. The #580 kept-traps remain untouched.
8. **[KEPT — by design]** Vestigial states — `campaign_verify_more` (golden-master parity), `campaign_intro` (legacy path), `campaign_charitable_giving` (constant only). Documented so nobody "tidies" them into the walk.

**Verification note:** backend fixes are test-pinned (`tests/Feature/AI/CampaignAuditFixesTest.php`); the `/m` + funnel JS fixes (5, 6) have no unit harness and need the usual csjones browser pass after the next dev deploy (fresh /m bundle required).

### Known / deferred (pre-existing, CSJ-owned)

- Web verify-bubble prompts render literal `**` (FynQuickReplies doesn't parse markdown) — shared with pensioncheck.
- "I've saved your bank accounts" label on a savings-only verify (pre-existing label nuance).
- Desktop has no achievements/milestones/history UI (spec §6 deferred); tax-savings milestone mints only on the /tax-strategy read.
- SaveTax `reentry:false` is a design choice — revisiting tax data post-walk goes through Advice Fyn + `/tax-strategy`, not the walk.

---

## 14. File inventory

| Path | Role |
|---|---|
| `public/pages/savetax.php` + `public/pages/js/savetax.js` | Live funnel — 5 questions, campaign stamp, localStorage + query-param handoff |
| `public/pages/savetax-v2.php`/`js/savetax-v2.js`, `savetax-plan-v2/3/4.php`, `js/savetax-plan(-v2).js` | Mockup variants (`no-store`) |
| `public/pages/savetax-plan.php` + `public/pages/js/savetax-plan-v4.js` | Plan page: server estimate → `window.SAVETAX_ESTIMATE`, allowances grid, register card |
| `app/Services/Marketing/SaveTaxEstimateService.php` | Banded estimate engine; 60% trap; TaxConfigService only |
| `routes/web.php:644-717` | Route block (`redirect.authed`), ordering vs /m + SPA catch-all |
| `app/Http/Middleware/{RebasePublicPageUrls,RedirectPhoneToMobile,PreviewWriteInterceptor,RedirectAuthenticatedToDashboard}.php` | Subdirectory rebase; phone→/m; preview exclusions; guest-only funnel |
| `resources/views/mobile-host.blade.php` | /m iframe host (`isFramableTo` guard) |
| `app/Http/Controllers/Api/AuthController.php` | `register`, `verifyCode` (user creation + funnel copy + consents), `resendCode` |
| `app/Http/Requests/RegisterRequest.php` | `funnel_answers.*` validation + `signup_source` allowlist |
| `app/Models/PendingRegistration.php` | 24h pending store; funnel_answers preservation |
| `app/Services/Auth/FunnelAnswersMapper.php` | Registration-time profile pre-fill |
| `resources/js/views/{Register,Login,Dashboard}.vue`, `components/Auth/VerificationCodeModal.vue` | funnelHandoff panel, verify modal, `openFyn=journey` dispatch |
| `resources/js/store/modules/aiChat.js`, `resources/js/router/index.js`, `resources/js/mScaffoldBridge.js` | `from` forwarding; iframe token bridge |
| `resources/mobile/views/Dashboard.vue`, `resources/mobile/mixins/onboardingChat.js`, `resources/mobile/components/MobileChrome.vue`, `resources/mobile/utils/editPrompt.js` | /m auto-open, SSE handling, verify pills, edit opener |
| `config/onboarding.php` | `campaign_map` (savetax entry `base_work`, `reentry:false`), `fyn_flow_enabled` |
| `app/Http/Controllers/Api/AiChatController.php` | `startOnboarding`, `sendMessage`/`streamQueuedMessage`/`action`, `routesToOnboardingDirector`, `levelUpFrame`, queue/lock |
| `app/Services/Onboarding/OnboardingStateMachine.php` | State table, section orders/maps/verify config, skip predicates, prompt builders, `enterCampaignVerify`, `BUBBLE_BREAK` |
| `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` | Corpus DATA (authoritative prompts; golden-master parity) |
| `app/Services/Onboarding/{OnboardingChatDirector,OnboardingPromptBuilder,OnboardingWorkflowTable,JourneyFieldResolver,FunnelIncomeBand,OnboardingValueInterpreter}.php` | Turn orchestration, tool arms, corpus parser, expenditure copy, band checks, parsers |
| `app/Agents/CoordinatingAgent.php` | All capture tool handlers |
| `fyn-memory/procedural/tool_schema/{onboarding,campaign,savings,data}/*.md` (+`.xai.md` LIVE) | Tool schemas — descriptions govern model behaviour |
| `app/Services/AI/{AdviceFyn,AiToolDefinitions,WriteIntentClassifier}.php`, `app/Services/AI/Loop/{FynLoop,ConcurrentTurnQueue}.php`, `app/Traits/HasAiChat.php` | Write-safety, handoff, queue, capture_write_result honesty |
| `app/Constants/UpdateRecordAllowlist.php` | Per-entity updatable-field allowlist |
| `app/Services/Coordination/{ComposedTaxPlanService,ComposedModulePlanService}.php` + `PlanSources/TaxStrategySource.php` + `app/Services/Tax/Strategies/*` | Advice/synthesis plan items |
| `app/Services/Mobile/{NextActionsService,MilestoneDetectionService}.php`, `app/Services/Gamification/MilestoneCollector.php` | Post-campaign affinity, milestones |
| `app/Console/Commands/CleanupPendingRegistrations.php` | Hourly pending expiry |
