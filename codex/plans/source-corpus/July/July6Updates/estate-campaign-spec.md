# Estate / Inheritance-Tax Campaign (Campaign #4) — Spec

*2026-07-06. The design spec for the estate-planning campaign, cloning the savetax/pensioncheck machinery exactly. Every claim is file:method-grounded against dev `9c9e7d2` (post-#612). Written to be implemented by an agent with NO further design decisions — the only CSJ-blessing items are marked DRAFT. Companion docs: `estate-campaign-plan.md` (the build plan — read AFTER this), `July/July3Updates/campaign-playbook.md` (F1–F15 formatting standard — BINDING), `July/July6Updates/pensionCampaign.md` + `saveTax.md` (current-state maps of the two live campaigns — the template), `July/July4Updates/pensioncheck-patch-notes-technical.md` (what the last clone learned).*

**Working campaign id / URL: `inheritancecheck` → `/inheritancecheck`** (≤32 chars — fits `users.active_campaign` string(32)). **DRAFT — CSJ confirms the name/URL at funnel-build time** (as with pensioncheck D2); every occurrence below uses the working id, and the implementer must not change any other identifier if CSJ renames it (one config key + routes + JS strings + prefixes).

**State prefix: `campaign4_*`** (savetax = `campaign_*`, pensioncheck = `campaign2_*`; `campaign3_*` is reserved for the investment campaign).

**Guiding principle (CSJ, standing): minimal questions, maximum value — the SaveTax discipline.** Every question must directly unlock advice the estate engine gives back (§6a maps each). Any question that doesn't earn its place is cut or gated.

---

## 1. The one design principle

**One estate section walk, data-presence skip predicates.** New users and existing users (savetax/pensioncheck graduates) run the SAME campaign states; every state carries a "do we already have this?" predicate. The delta IS the skip logic — no forked flow. This is the proven pensioncheck pattern (`OnboardingStateMachine::campaignSections('pensioncheck')`, data-presence skips at `skipSectionIfIncomeKnown` etc.).

## 2. What the engine needs (readiness × strategies)

**Readiness** — `app/Services/Estate/EstateDataReadinessService::assess()`:
- **BLOCKING**: `date_of_birth`, `marital_status`, `at_least_one_asset` (property OR investment OR savings OR DC/DB pension row exists).
- **WARNING**: `property_data` (property with `current_value > 0`), `liabilities`, `family_members`, `gifts_recorded` (`Gift` rows), `will_status` (`Will` row), `power_of_attorney` (LPA row), `residency_status`.
- **INFO**: `income_data`, `life_insurance_policies`.

**Strategy catalogue** — `EstateStrategySource` (`app/Services/Coordination/PlanSources/EstateStrategySource.php`, `moduleKey()='estate'`) → `EstateActionDefinitionService::evaluateActions()`. The 4 strategy rows (seeded `EstateActionDefinitionSeeder::getStrategyDefinitions()`, keys `strategy_<type>`):

| strategy_type | claim_tier | locks on (required_data absent) |
|---|---|---|
| `make_a_will` | mechanical | `will_in_place` |
| `register_lpa` | mechanical | `lpa_registered` |
| `reduce_iht_exposure` | judgement | `estate_value_known` |
| `gift_to_reduce_estate` | judgement | `estate_value_known` |

Plus the 8 live agent triggers (`no_will` critical, `no_lpa`/`no_lpa_health`, `iht_exceeds_nrb` — sets `estimated_impact` = excess × standard rate, `gifts_pet_window`, `policy_not_in_trust`, `trust_review_due`, `beneficiary_review`). Adapter: `EstateRecommendationAdapter` (`estimatedAnnualTaxSaved` always null — IHT saving rides in `extra['estimated_iht_saving']`; **synthesis therefore emits no "— saves around £X a year" suffixes and no combined-savings line — same as pensioncheck**).

## 3. The data delta (engine needs × existing user has × campaign action)

"Existing user" = completed savetax (income, DOB, marital, expenditure, savings/ISA/investment accounts, spouse) and possibly pensioncheck (pensions, state pension, goals).

| Engine need | Existing user has it? | Campaign action |
|---|---|---|
| DOB (BLOCKING) | YES (`campaign_dob`) | Skip (`skipIfDobSet` exists) |
| Marital status (BLOCKING) | YES (funnel mapper / base states) | Skip (funnel seeds it for fresh users too) |
| ≥1 asset (BLOCKING) | YES (savetax captured accounts) | Fresh users: property + assets sections capture it |
| Property + value (WARNING, RNRB) | PARTIAL — savetax never asks property | **Ask (skip if `properties` rows exist)** |
| Savings/investments (estate value) | YES for savetax graduates | Skip when rows exist; fresh users asked |
| Family members / direct descendants (RNRB) | NO — savetax asks dependants yes/no only on base path | **Ask (skip if `family_members` rows exist)** |
| Will status (WARNING → `make_a_will`) | NO | **Ask (everyone; skip if a `wills` row exists — `has_will=false` rows COUNT as answered)** |
| LPA (WARNING → `register_lpa`) | NO | **Ask (everyone; skip if LPA rows exist)** |
| Gifts in 7 years (→ `gifts_pet_window`, NRB deduction) | NO | **Ask (everyone; skip if `gifts` rows exist)** |
| Liabilities/mortgages | PARTIAL (mortgage rides on `create_property`) | Captured with property; no dedicated question |
| Income / expenditure | YES (savetax) — INFO only for estate | **Not in the walk** (estate-lean; readiness treats income as INFO) |

**Net delta for a savetax graduate: ~4–5 questions** (property, family if unknown, will, LPA, gifts). A cold new user gets ~7–8 questions. Comparable to pensioncheck's delta discipline.

## 4. End-to-end flow (both user classes)

```
NEW USER                                          EXISTING USER (completed a campaign)
Homepage "Could your estate face an                Plan-page "Already with Fynla? Log in"
inheritance tax bill?" card                          → /login?redirect=/dashboard?openFyn=journey
  → FUNNEL /inheritancecheck (6 questions, §7)         &from=inheritancecheck
  → PLAN /inheritancecheck/plan                      → dashboard openFyn handler → onboarding/start
      EstateEstimateService: estimated IHT               (from=inheritancecheck) — conditional 409
      exposure £X (§7.2)                                 BYPASSED (reentry:true) → active_campaign
  → register (+funnel_answers, campaign stamped)         stamped → step=campaign4_existing_recap
  → verify → dashboard?openFyn=journey&              → "Welcome back… here's what I already
      from=inheritancecheck                              have… Is that all still right?"
  → onboarding/start → entry base_personal (§5)      → [Yes] → DELTA WALK (gap questions only)
                BOTH → the SAME section walk: essentials → property → assets
                       → will → lpa → gifts → synthesis → terminal
  → per section: capture → verify announce → navigate → Continue/Edit pills → confirm
      → section advice ("added to your actions list") → next section
  → SYNTHESIS "Here's your estate plan…" (mirrors the composed ESTATE plan)
  → TERMINAL campaign4_terminal "We've built your estate picture, {first_name}."
      [Take me to my estate plan → /estate]
      (web /estate exists natively — router/index.js:917; /m /estate exists — router.js:57)
  → POST-CAMPAIGN applyCampaignAffinity('inheritancecheck' → 'estate')
```

**Entry state for fresh users: `base_personal`** — NOT savetax/pensioncheck's `base_work`. The estate walk has no income section; `base_personal` captures DOB + marital in one grouped turn (`buildPersonalPrompt` already handles campaign funnel recaps and skips when both are set — `skipIfPersonalComplete`). The campaign_map `entry` value makes this a config choice (G1 generalisation, already built).

## 5. The section walk (states, questions, tools, skips)

`CAMPAIGN_SECTION_ORDERS['inheritancecheck'] = ['essentials', 'property', 'assets', 'will', 'lpa', 'gifts']` — no income, no expenditure, no spouse-income section (marital status is enough for NRB doubling; spouse asset splitting is savetax territory).

**Section map (`campaignSections('inheritancecheck')`):**

| section | entry state | whole-section skip predicate (all data-presence) |
|---|---|---|
| essentials | `base_personal` (reuse) | `skipIfPersonalComplete` handles the states; section skip: DOB AND marital both set AND `family_members` answered — implement `skipSectionIfEssentialsKnown` |
| property | `campaign4_property` | `skipSectionIfPropertyKnown` — `$user->properties()->exists()` |
| assets | `campaign4_other_assets` | `skipSectionIfEstateAssetsKnown` — any of savings_accounts / investment_accounts / assets rows exist |
| will | `campaign4_will` | `skipSectionIfWillKnown` — `Will::where('user_id',$id)->exists()` (**row-existence, NOT has_will=true** — a recorded "no will" must not re-ask; the no-will strategy fires from the row) |
| lpa | `campaign4_lpa` | `skipSectionIfLpaKnown` — `LastingPowerOfAttorney::where('user_id',$id)->exists()` |
| gifts | `campaign4_gifts` | `skipSectionIfGiftsKnown` — `Gift::where('user_id',$id)->exists()` |

### Per-state table

All prompts follow F1 (question line bold), F9 (retry names the format), F15 (corpus + `inCodeStates()` in lockstep). Copy is DRAFT (like pensioncheck's was) — the implementer ships it verbatim; CSJ polishes later.

**`base_personal`** (reused; grouped_extract, `capture_personal_details`) — existing state, existing skips. For campaign-path funnel arrivals it already fires the funnel recap (`buildPersonalPrompt` → campaign branch); the inheritancecheck funnel recap builder is specified in §7.3.

**`campaign4_family`** (NEW; grouped_extract, `capture_dependants` — the existing extraction tool that writes `family_members`) — part of the essentials section, next after `base_personal`:
- Prompt: `"**Do you have children or other dependants?** Just names and ages is fine — it matters because passing your home to direct descendants can add £{rnrb} of tax-free allowance."` — the £ figure interpolated from `TaxConfigService->getInheritanceTax()['residence_nil_rate_band']` (Rule 2; build the prompt via a callable, never hardcode).
- Retry: `'Just tell me who — for example "two children, Alice 7 and Bob 4", or say "no children".'`
- `advance_on_answered_question: true` — "no children" advances without a write.
- Skip: `skipIfFamilyKnown` — `family_members` rows exist OR `users.has_dependants === false` (check the actual column the base dependants flow writes — see plan Slice C task).
- Writes: `family_members` via the existing dependants handler.

**`campaign4_property`** (NEW; delegated) — property section entry:
- Prompt: `"Now your home. **Do you own the property you live in? If so, roughly what's it worth, and how much is left on the mortgage?** Renting? Just say so and we'll move on."`
- Tools: `toolsForFocus('inheritancecheck')` (see §8 — includes `create_property`/`create_mortgage`); `create_property` requires only `property_type` + `current_value` and auto-creates the mortgage from the same call (`handleCreateProperty`, CoordinatingAgent:2647).
- `advance_on_answered_question: true` ("I rent" advances).
- `record_context: 'property'` if properties exist (update-vs-create steering — plan defines the appendix arm).
- Next: `enterCampaignVerify($user, 'property')`.

**`campaign4_other_assets`** (NEW; delegated) — assets section entry:
- Prompt: `"**Roughly what do you have in savings and investments?** Account-by-account is best — for example \"£15,000 in a Cash ISA at Halifax, £30,000 in a Vanguard Stocks & Shares ISA\". Ballpark figures are fine."`
- Tools: `create_savings_account`, `create_investment_account`, `create_holding`, `create_asset` (for valuables/collectibles the user volunteers).
- `advance_on_answered_question: true` ("nothing" advances — but then BLOCKING `at_least_one_asset` may still be unmet for a renter with no accounts; the synthesis degrade line covers the empty-plan case, and the composed plan's locked/unlock cards surface what's missing).
- Next: `enterCampaignVerify($user, 'assets')`.

**`campaign4_will`** (NEW; delegated):
- Prompt: `"**Do you have a will?** If so, tell me who the executor is and who inherits. If not, just say so — it's the single most important piece of estate planning to fix."`
- Tools: `create_will` (requires `executor_name`), `update_will`.
- **"No will" handling (critical semantics)**: a Will row with `has_will=false` is a recorded answer (`MilestoneDetectionService` comment: "A Will row is a profile answer — has_will=false means 'no will'"). The plan adds a deterministic no-will write: when the user answers a clear "no", the director's answered-question advance alone would leave NO row (and the section would re-ask on re-entry). **Spec decision: on a substantive "no" at this state, the handler path writes `Will::updateOrCreate(['user_id'], ['has_will'=>false])`** — implement as a small `capture_will_status`-style branch in the state's advance hook or instruct the model (schema description) to call `create_will` with `has_will=false`… `create_will` requires `executor_name`, so it cannot record "no". → **The plan adds ONE new grouped-extract tool `capture_will_status`** (the only new tool in this campaign) writing `wills.has_will` true/false + optional executor fields, mirroring `capture_state_pension`'s write-guard discipline. See plan Slice C.
- Skip: section skip covers it.
- Next: `enterCampaignVerify($user, 'will')`.

**`campaign4_lpa`** (NEW; delegated):
- Prompt: `"**Do you have a Lasting Power of Attorney?** There are two kinds — one for property and financial affairs, one for health and welfare. Tell me which you have and whether they're registered, or say \"no\" and we'll note it."`
- Tools: `create_power_of_attorney` (requires `lpa_type` + `primary_attorney_name`; schema mandates status, defaults "draft"), `update_power_of_attorney`.
- `advance_on_answered_question: true` ("no" advances; the `no_lpa` triggers fire from absence — unlike the will, absence-of-row IS the signal here and re-asking on re-entry is prevented by… nothing. **Mitigation (same shape as pensioncheck's `campaign2_pension_db`): accept the re-ask risk for v1** — an LPA-less re-entrant sees the question again; the section skip fires as soon as any LPA row exists. Documented, not a bug.)
- Next: `enterCampaignVerify($user, 'lpa')`.

**`campaign4_gifts`** (NEW; delegated):
- Prompt: `"**Have you given away any large sums or valuable items in the last seven years?** Gifts can fall back into your estate for inheritance tax. Tell me roughly what, when, and to whom — or say \"no\"."`
- Tools: `create_estate_gift` (requires `gift_date`, `recipient`, `gift_type`, `gift_value`; schema defaults `gift_type` to "pet").
- **Hallucination guard (the `capture_state_pension` lesson)**: the schema requires `gift_date` — the model may invent one from "a few years ago". The plan hardens the handler: reject a write when the user gave no date signal (see plan Slice C trap task).
- `advance_on_answered_question: true`.
- Next: `enterCampaignVerify($user, 'gifts')`.

**`campaign4_existing_recap`** (NEW; bubbles `[Yes, that's right | Something's changed]`) — the re-entry gate, cloned from `campaign2_existing_recap` (`buildExistingRecapPrompt`, OnboardingStateMachine:2310-2383; router `nextFromExistingRecap` — REUSE the generic router + `firstCampaignSection`, they're selection-driven already):
- Builder `buildEstateRecapPrompt` — deterministic DB reads (F5 spirit): one line per property (`"{type} worth £X"` via `properties`), savings/investments totals (via the stores), will status (`"You have a will"` / `"No will recorded"`), LPA lines, gifts count (`"3 gifts recorded in the last seven years"`), marital + children lines. Lead for completed users: `"Welcome back, {firstName}. Let's take a proper look at your estate. Here's what I already have from you:"` then `**Is that all still right?**`.
- `'changed'` → the generic recap-edit path (stamps `verify_section='recap'` → `campaign_verify_edit` → confirmed edit re-enters `firstCampaignSection` — all shipped in #612; the estate campaign only extends `verifyEditRecordContext`/`verifyEditSnapshot`/`verifyEditFocus` with a `'recap'`-for-estate awareness — see plan).

**Advice states** (`campaign4_advice_property`, `campaign4_advice_assets`, `campaign4_advice_will`, `campaign4_advice_lpa`, `campaign4_advice_gifts`) — `turn_type: 'advice'`, auto-advancing, `next` = **callable-string, never a closure** (PR #504 law). Mapping in §6.

**`campaign4_terminal`** — `turn_type: terminal`, prompt `"We've built your estate picture, {first_name}."`, `navigate_to: '/estate'`, next `done`. Handled by the generic `emitTerminalNavigationTurn` (completion guards, active_campaign clear — all shipped); `terminalNavigationBubble` gains the arm `['view_estate', 'Take me to my estate plan', '/estate']`.

### Verify config (`campaignVerifyConfig('inheritancecheck')`)

| section | route | loop-back entry |
|---|---|---|
| essentials | `null` (inline confirm — no single screen shows DOB+family) | `base_personal` |
| property | `/net-worth` | `campaign4_property` |
| assets | `/net-worth` | `campaign4_other_assets` |
| will | `/estate` | `campaign4_will` |
| lpa | `/estate` | `campaign4_lpa` |
| gifts | `/estate` | `campaign4_gifts` |

**`/m` allowlist**: `ONBOARDING_NAV_ROUTES` (resources/mobile/mixins/onboardingChat.js:26) currently lacks `/estate` and `/net-worth` — **both must be added** or verify navigation silently no-ops (`handleOnboardingNavigation` early-returns). Confirmed against the mixin at dev tip.

**Tier caveat (FLAGGED — default accepted):** `/m` `/estate` full mode is Tier-2 gated (`TeaserGate::isFull($user,'estate')` in `EstateController`); a free campaign user sees the TEASER — which shows exactly the campaign's headline ("Estimated Inheritance Tax liability" via `EstateIhtExposureDetector`) but NOT the will/gifts card. **Default: accept** — the teaser is on-message and doubles as the Tier-2 upsell moment. The will/lpa/gifts verify screens therefore show the IHT number, not the captured record; the in-chat deterministic read-back (F5) remains the record confirmation. CSJ may instead bless a campaign carve-out of the gate — do NOT build one unprompted (Rule 16).

## 6. Per-section advice + synthesis

`ESTATECHECK_SECTION_STRATEGY_TYPES` (clone of `PENSIONCHECK_SECTION_STRATEGY_TYPES`, OnboardingChatDirector:950-954):

```php
'property'  => ['reduce_iht_exposure'],
'assets'    => ['reduce_iht_exposure'],
'will'      => ['make_a_will'],
'lpa'       => ['register_lpa'],
'gifts'     => ['gift_to_reduce_estate'],
// 'essentials' absent — silent (null → auto-advance), like pensioncheck 'income'
```

Routed via a `buildEstateSectionAdvice` clone of `buildRetirementSectionAdvice` (:1042): `ComposedModulePlanService::forSource(app(EstateStrategySource::class), $user)`, ≤2 items, claim-tier prefix, closing "I've added this to your actions list to come back to later." **Sections not in the map return null — no estate path may reach the savetax tax builders** (the dea2b8a cross-campaign-leak law).

**Synthesis**: `buildSynthesisAdvice` gains the `inheritancecheck` arm — composed ESTATE plan, lead-in `"Here's your estate plan, built from what you told me — in the order I'd tackle it:"`, F4 bullets (no £-suffix — `estimatedAnnualTaxSaved` is always null for estate items), FCA signpost line, degrade fallback `"That's your estate details saved, {firstName}. You'll find your full estate picture on the next screen."`

### 6a. Why each question earns its place

| Question | Advice it unlocks |
|---|---|
| DOB + marital (essentials) | BLOCKING readiness; NRB doubling for married/civil partnership; actuarial projection |
| Children/dependants | RNRB eligibility (£175k via `residence_nil_rate_band`) — without it the IHT number overstates by up to £70k×2 |
| Property + mortgage | `iht_exceeds_nrb` trigger + RNRB (main residence requirement); estate composition |
| Savings/investments | `estate_value_known` unlocks `reduce_iht_exposure` + `gift_to_reduce_estate`; the headline IHT figure |
| Will | `make_a_will` (mechanical, critical `no_will` trigger) |
| LPA | `register_lpa` (both `no_lpa` triggers) |
| Gifts (7 years) | `gifts_pet_window` + NRB deduction correctness — without it the IHT number can be WRONG, not just incomplete |

**Deliberately NOT asked** (v1): trusts (module UI territory; `trust_review_due` fires for users who add them later), life-policies-in-trust (protection module), charitable-legacy intent (36% rate — `iht_profiles.charitable_giving_percent`, module UI), domicile/residency (WARNING only), business interests/chattels (the model may capture volunteered ones via the armed tools — fine, never prompted).

## 7. Funnel + plan page

### 7.1 The six questions (clone the pensioncheck page pair; copy is DRAFT)

| # | Key | Question | Options (`data-value`) |
|---|---|---|---|
| 1 | `spouse` | "Do you have a spouse?" | `yes` / `no` |
| 2 | `children` | "Do you have children or grandchildren?" | `yes` / `no` |
| 3 | `home` | "Do you own your home?" | `no` ("No — I rent"), `under_300k`, `300k_500k`, `500k_1m`, `over_1m` |
| 4 | `other_assets` | "Roughly what do you have in savings, investments and pensions?" | `under_50k`, `50k_250k`, `250k_500k`, `over_500k` |
| 5 | `will` | "Do you have a will?" | `yes` / `no` |
| 6 | `gifts` | "Have you given away large sums in the last seven years?" | `yes` / `no` / `not_sure` |

Answers object initialised `{ campaign: 'inheritancecheck', spouse: null, children: null, home: null, other_assets: null, will: null, gifts: null }` — campaign stamp baked in at line 1 of the JS (the savetax/pensioncheck pattern). localStorage key `inheritancecheck_answers`. utm capture block included (the S6 pattern — same sessionStorage key `fynla.signup_source`, same allowlist).

### 7.2 `EstateEstimateService` (app/Services/Marketing/)

Clone `PensionEstimateService`'s shape (single `estimate(array): array`, banded midpoints as marketing consts, tax values ONLY via TaxConfigService). Maths lifts `EstateIhtExposureDetector::detect()`:

- Midpoints: home `no`→0, `under_300k`→200,000, `300k_500k`→400,000, `500k_1m`→750,000, `over_1m`→1,250,000; other_assets `under_50k`→25,000, `50k_250k`→150,000, `250k_500k`→375,000, `over_500k`→650,000.
- Allowances: `nrb = getInheritanceTax()['nil_rate_band']`; `rnrb = getInheritanceTax()['residence_nil_rate_band']` included ONLY when `home !== 'no'` AND `children === 'yes'`; married (`spouse === 'yes'`) doubles both ("as a couple, on the second death" — the transferable-NRB assumption, stated in the disclaimer).
- Headline: `estimated_iht = max(0, (homeMid + otherMid) − allowances) × getInheritanceTax()['standard_rate']`.
- Returns `{estimated_iht, estate_value_assumed, allowances_total, nrb_used, rnrb_included, married_doubling, will_note, gifts_note, tax_year}`. `will_note`: no will → "Without a will, the intestacy rules decide who inherits — and they don't always match what you'd choose." `gifts_note` for `yes`/`not_sure`: "Gifts made in the last seven years can fall back into your estate — we'll check them properly." **RNRB taper (estates > £2m) is deliberately ignored in the teaser** (band midpoints max at £1.9m combined) — note it in the service docblock.
- Zero-IHT case (headline £0): the hero flips to the positive frame — "Your estate looks to be inside the tax-free allowances — see exactly where you stand" (plan page handles `estimated_iht === 0` with an alternate hero block; still registers).

### 7.3 Plan page + registration pull-through

- Injection: `window.INHERITANCECHECK_ESTIMATE = <?= json_encode(..., HEX flags) ?>` — never echo query values; `esc()` before `innerHTML` (the pensioncheck XSS pattern verbatim).
- Register card: button DRAFT `"Start my estate plan — free"`; note "Takes you straight to your dashboard with Fyn open, ready to protect what you've built."; `signup_source` from the S6 helper; "Already with Fynla? Log in" → `/login?redirect=` + encoded `/dashboard?openFyn=journey&from=inheritancecheck`.
- `RegisterRequest` gains the inheritancecheck keys: `home`, `other_assets`, `will`, `gifts`, `children` (all `nullable|string|max:20`); `spouse` already validated. `prepareForValidation` auto-accepts the campaign once the `campaign_map` entry exists (it derives the allowlist from `config('onboarding.campaign_map')` keys — shipped in #612).
- `FunnelAnswersMapper`: `spouse` → `marital_status` (existing branch). **Add**: nothing else — `children`/`will`/`gifts` stay in `funnel_answers` only (the walk captures real rows; a funnel yes/no must never fake a DB record).
- Funnel recap (fresh-user turn 1): `buildInheritancecheckFunnelRecapPrompt` clone of `buildPensioncheckFunnelRecapPrompt` — bullets from spouse/children/home band/assets band/will/gifts, goal line `"…to build your estate plan I just need a few more details — this usually takes about {3-4} minutes."`, then BUBBLE_BREAK + the `base_personal` question. Fired from `buildPersonalPrompt`'s campaign branch (entry is `base_personal`, which already hosts the funnel-recap seam — G5).

## 8. Config + seam wiring (all pre-generalised; one entry each)

| Seam | Entry |
|---|---|
| `config/onboarding.php` `campaign_map` | `'inheritancecheck' => ['selection' => 'inheritancecheck', 'entry' => 'base_personal', 'reentry' => true, 'reentry_entry' => 'campaign4_existing_recap']` |
| `RedirectPhoneToMobile::CAMPAIGN_PREFIXES` | `'inheritancecheck'` (**the P1 lesson — do NOT forget**) |
| `NextActionsService::CAMPAIGN_AFFINITY` | `'inheritancecheck' => 'estate'` (items carry `module => 'estate'`; `moduleRoute` already maps `'estate' => '/estate'`) |
| `OnboardingPromptBuilder::toolsForFocus` | new arm `'inheritancecheck' => ['create_property', 'create_mortgage', 'create_savings_account', 'create_investment_account', 'create_holding', 'create_asset', 'create_will', 'update_will', 'create_power_of_attorney', 'update_power_of_attorney', 'create_estate_gift', 'capture_will_status']` (+ the universal `update_profile`/`update_record`). The existing `'estate'` arm is NOT sufficient (lacks will/LPA tools) and must NOT be modified (advice-mode focus uses it) |
| `ONBOARDING_NAV_ROUTES` (mixin) | add `'/estate'`, `'/net-worth'` |
| `sectionLabel` | `'property' => 'property', 'assets' => 'savings and investments', 'will' => 'will', 'lpa' => 'Lasting Power of Attorney details', 'gifts' => 'gifts'` |
| `describeStep` | one label per campaign4 state (the S3 lesson) |
| `terminalNavigationBubble` | `'/estate' => ['view_estate', 'Take me to my estate plan', '/estate']` |
| Homepage card | `feature-inheritancecheck` beside the pensioncheck card — same failure-tolerant try/catch, representative persona (spouse=yes, children=yes, home 300k_500k, assets 50k_250k), headline DRAFT "Could your estate face an inheritance tax bill?" CTA "Check my estate" |
| Milestones | ALREADY SHIPPED (`will_in_place`, `lpa_in_place`, `estate_plan_started`, module profile id 5) — E2E verifies they mint; no new wiring |

**Tools**: every estate tool already exists with `.xai.md`, is in `AdviceFyn::WRITE_TOOLS` AND `captureToolSet` — the ONLY new tool is `capture_will_status` (§5, will state), which must be added to corpus (both providers) + `AiToolDefinitions::ORDER` + dispatch + `WRITE_TOOLS` + `captureToolSet` + golden-master re-record.

## 9. Decisions (locked by symmetry — CSJ may override before build)

- **D1 — Re-entry: enabled** (`reentry:true`, recap entry) — the pensioncheck mechanism, now generic.
- **D2 — Headline metric: estimated IHT exposure £X** (zero-case flips to the inside-allowances frame). Campaign name/URL `inheritancecheck` DRAFT.
- **D3 — Walk scope: estate-lean** — no income/expenditure/spouse-asset sections; trusts/policies/charity deliberately out (§6a).
- **D4 — Landing: existing `/estate` screen** (web native + /m native). Free users see the Tier-2 teaser — accepted default, flagged for CSJ.
- **D5 — One new tool only** (`capture_will_status`); everything else reuses the existing estate tool surface.

## 10. Build inventory → see `estate-campaign-plan.md`

Slices A–D mirror the pensioncheck plan: A = config/substrate wiring, B = public surfaces, C = the walk + states + tool, D = E2E gate (fresh walk, delta walk, integrity, savetax AND pensioncheck regression). The plan carries the full trap list (30 items) accumulated from PR #610 + the 2026-07-06 audit fixes — read it before writing any code.
