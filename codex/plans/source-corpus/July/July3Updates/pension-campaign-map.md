# Pension Campaign (Campaign #2) — Map

*2026-07-03. The design map for the pension planning campaign, per CSJ's brief: pension-centric; new users get the correct pension questions; existing users are asked only for the missing data. Grounded file:line against dev tip `c836fb9`. Companion docs: `campaign-playbook.md` (template + F1–F15), `campaign-blueprint.md` (seams + §existing-users), `wp5c-milestones-spec.md`.*

**Terminology:** "existing user" throughout = a user who already completed the SaveTax onboarding (`users.onboarding_completed = true`). Their income, date of birth, spouse details, expenditure and pension contribution percentages are already in the database — the campaign must never re-ask any of it.

**Guiding principle (CSJ, 2026-07-03): minimal questions, maximum pension value — the SaveTax discipline.** Every question in the walk must directly unlock a specific piece of advice the retirement engine gives back (§5a maps each question to the advice it powers). Any question that doesn't earn its place is cut or gated.

**Status: DECISIONS LOCKED (CSJ, 2026-07-03 — see §9). Ready for the implementation plan.**

---

## 1. The one design principle

**One pension section walk, data-presence skip predicates.** New users and existing SaveTax users run the *same* campaign states; every state carries a "do we already have this?" predicate. New users skip almost nothing (funnel gating only, as SaveTax does today); existing users skip everything SaveTax already captured and answer only the pension-specific gaps. No second question set, no forked flow — the delta *is* the skip logic.

This works because:
- The section/verify machinery is keyed off `onboarding_fyn_context` (`verify_section` etc.), not the campaign name — confirmed at `OnboardingStateMachine.php:969–1000` (blueprint's reusability claim holds).
- SaveTax already has per-state skips of exactly this shape (`campaign_dob` skips if `users.date_of_birth` set; `campaign_occupational_scheme` skips on employment status).

## 2. The data delta (what existing users have vs what the engine needs)

Retirement engine requirements from `RetirementDataReadinessService::assess()` (`app/Services/Retirement/RetirementDataReadinessService.php:33–400`) crossed with the SaveTax capture inventory:

| Engine need | Level | Existing user has it? | Campaign #2 action |
|---|---|---|---|
| Date of birth (`users.date_of_birth`) | BLOCKING | YES (`campaign_dob`) | Skip (predicate already exists) |
| Marital status (`users.marital_status`) | BLOCKING | YES (base_personal / funnel) | Skip |
| Gross income (`job_employment` rows + `users.annual_*_income`) | BLOCKING | YES (`capture_work_details`) | Skip; one-line "still current?" recap for existing users (see §5) |
| ≥1 pension with a value | WARNING | PARTIAL — workplace DC has contribution % + salary-sacrifice flag but **no pot value**; SIPP/personal has value | **Ask: pot value backfill** (update existing `dc_pensions` rows) |
| Defined Benefit pensions (`db_pensions`) | — | NO — SaveTax never asks | **Ask (everyone)** |
| Target retirement age (`retirement_profiles.target_retirement_age`) | WARNING | NO | **Ask (everyone)** |
| Target retirement income (`retirement_profiles.target_retirement_income`) | WARNING (soft-blocks quality advice) | NO | **Ask (everyone)** |
| Expenditure (`users.monthly_expenditure`) | WARNING | YES (`base_expenditure`) | Skip |
| State Pension forecast + NI years (`state_pensions`) | INFO (drives `no_state_pension_forecast` + NI gap-fill strategies) | NO | **Ask (everyone)** |
| Pension contribution history (carry-forward) | Tier-4 tax | YES (parked facts `pension_history`) | Skip if parked facts present |
| Flexible access / Money Purchase Annual Allowance (`dc_pensions.has_flexibly_accessed`) | Tier-4 | NO | **Ask only if age ≥ 55** |
| Spouse pension pots | INFO (if married) | PARTIAL — dual-earner existing users have `spouse_pension_input_annual` (contributions), no pots | **Ask if married** |
| Employment status | INFO | YES | Skip |

**Net delta for an existing user: 5–7 questions** (pot values, DB yes/no, State Pension, target age, target income, +flexible access if 55+, +spouse pots if married). A cold new user gets the full walk (~12–14 questions) — comparable to SaveTax.

## 3. The end-to-end flow (both user classes)

```
NEW USER                                      EXISTING USER (completed SaveTax)
Ad / CTA                                      Ad / email / dashboard nudge CTA
  → pension funnel (public pages)               → (if via public funnel: "already with
      Q1 employment · Q2 income band ·             Fynla? Log in" path on the plan page)
      Q3 age band · Q4 pension types held       → dashboard?openFyn=journey&from=pension
      (multi) · Q5 pot band · Q6 spouse         → POST onboarding/start (from=pension)
  → pension plan/estimate page                      user.onboarding_completed=true
      (register card; headline metric TBD §9)       + campaign re-entry enabled
  → register (+funnel_answers, stamped              → stamp users.active_campaign='pension',
      funnel_answers.campaign='pension')              set entry step, stream campaign intro
  → verify → dashboard?openFyn=journey              (replaces today's 409 at
      &from=pension                                  AiChatController.php:587)
  → onboarding/start → campaign_map match
      → entry state (income-first)
                    BOTH → the SAME section walk (§5), skip predicates do the rest
  → per section: capture → ONE gate → verify announce → navigate to /m screen
      (+nudge) → Continue/Edit pills → confirm → section advice → next
  → synthesis (mirrors composed retirement plan items — buildSynthesisAdvice is
      already campaign-agnostic)
  → terminal → retirement plan landing page (web + /m, §7)
  → post-campaign: applyCampaignAffinity('retirement') puts retirement actions first
```

## 4. Existing-user re-entry mechanism — RECOMMENDATION: option (a), minimal form

Blueprint §existing-users deferred (a) lightweight re-entry vs (b) advice-surface prompt overlay. The research settles it:

- `handleInlineCapture` (option b's write path) is **single-turn atomic** (`OnboardingChatDirector.php:3259–3384`) — a scripted 5–7-question delta walk with skips, verify-navigate, and per-section advice would need a brand-new multi-turn orchestrator bolted onto advice mode. That is rebuilding the state machine badly (exactly the substitute-a-cheaper-approximation trap, Rule 16).
- Option (a) reuses everything that already works: states, skip predicates, the canonical verify sequence, F1–F15 formatting, deterministic read-backs, gamification per state.

**Minimal mechanism:**
1. New nullable column `users.active_campaign` (string). NOT a context-JSON key — the dispatch guard runs per message and shouldn't parse JSON.
2. Dispatch guard (`AiChatController.php:236–238`) gains one OR-branch: route to the director when the existing 3-part predicate holds **or** (`active_campaign !== null && onboarding_fyn_step !== null && fyn_flow_enabled`). `onboarding_completed` stays `true` throughout — never touched.
3. `POST /api/ai-chat/onboarding/start` (`:587`): for a completed user whose `from=` resolves to a re-entry-enabled campaign, instead of the 409 → stamp `active_campaign`, set `onboarding_fyn_step` to the campaign entry state, stream the (existing-user-flavoured) campaign intro. All other completed-user cases keep the 409.
4. Exit paths: campaign terminal AND the "Something else" pause (`handleSomethingElseAction`, `:453`) null **both** `onboarding_fyn_step` and `active_campaign` → user falls back to Advice Fyn exactly as today. Resume = re-invoke start (the existing `resume` event machinery at `:613–619` extends naturally).

**Write-safety notes (must go in the spec):**
- No new write surface: the director IS the one write state; re-entry routes qualifying users to it. Advice Fyn stays read-only, catalogue-strip untouched, INV-2.4.1 untouched.
- `00-canonical.md`'s 3-part dispatch predicate is the canonical contract — it must be **amended** (not silently diverged from) to document the re-entry branch.
- CoALA (`coala` branch, GroundGate write-gating at dispatch) must compose with this when it lands: the re-entry branch selects the write surface, so GroundGate's surface determination needs the same OR-branch. Flag in the CoALA merge checklist.
- `/m` note: the mobile onboarding mixin does NOT pass `from` (`resources/mobile/mixins/onboardingChat.js:77` sends `{}`). New users are covered server-side by the `funnel_answers.campaign` stamp (generalisation G2, §8). Existing-user re-entry on /m needs the deep-link (`/m?to=...`) to carry the campaign so start receives it — one mixin change to forward a `from` param. Rule 19: in scope.

## 5. The section walk (states, questions, tools, skips)

Proposed `CAMPAIGN2_SECTION_ORDER`: **income → pensions → state_pension → retirement_goals → spouse → expenditure**. Income-first is kept deliberately (Annual Allowance, employer match %, tax relief and contribution-capacity advice all need real income figures; existing users skip it anyway). Savings/investments sections are **excluded** from the pension walk v1 — the retirement engine doesn't block on them, and existing users already have them (D3, decided).

| # | State (new unless marked) | Question (F1: question line bold) | Tool | Writes | Skip predicate |
|---|---|---|---|---|---|
| 0 | `campaign2_existing_recap` (existing users only) | Point-form recap of held data (income, pensions, spouse) + "**Is that all still right?**" | none (bubbles) | — | Skip for new users; skip nothing for existing users — this replaces their income section |
| 1 | `base_employment`/`base_work` (reuse) | employment + employer/role/income | `capture_work_details` (exists) | `job_employment` | Skip if `job_employment` rows exist |
| 2 | `campaign_dob` (reuse) | date of birth (F8 confirm-back) | `capture_personal_details` (exists) | `users.date_of_birth` | Skip if set (existing behaviour) |
| 3 | `campaign_occupational_scheme` (reuse) | workplace pension: %, match, salary sacrifice | `create_pension`, `capture_salary_sacrifice` (exist) | `dc_pensions` | Skip if workplace `dc_pensions` row exists **and** has a pot value |
| 4 | `campaign2_pension_pots` | "**What's the current value of your [scheme] pension?**" per pension missing `current_fund_value` | `update_record` (exists, `data/update_record.md`) | `dc_pensions.current_fund_value` | Skip if all pensions have values (i.e. most new users, who give values at create) |
| 5 | `campaign_pension_contribs` (reuse) | personal/SIPP pots + contributions | `create_pension` (exists) | `dc_pensions` | Skip only via "none" answer (advance_on_answered_question) |
| 6 | `campaign2_pension_db` | "**Do you have any final salary or other defined benefit pensions?**" → accrued annual pension, normal retirement age, scheme type | `create_pension` (exists — covers DB fields) | `db_pensions` | Skip if `db_pensions` rows exist; funnel Q4 can pre-gate for new users |
| 7 | `campaign_pension_history` (reuse) | 3-year gross contributions (carry-forward) | `capture_pension_history` (exists) | parked facts | Skip if parked facts `pension_history` present, **or income below the higher-rate threshold** — carry-forward advice only helps people who could out-contribute the standard Annual Allowance |
| 8 | `campaign2_flexible_access` | "**Have you taken any money out of a pension?**" (Money Purchase Annual Allowance) | `update_record` (exists) | `dc_pensions.has_flexibly_accessed` | Skip if age < 55 or already flagged |
| 9 | `campaign2_state_pension` | "**Do you know your State Pension forecast?**" (+ NI qualifying years; point at gov.uk forecast if unknown) | **NEW: `capture_state_pension`** | `state_pensions` (forecast_annual, ni_years_completed) | Skip if `state_pensions` row exists |
| 10 | `campaign2_retirement_goals` | "**When would you like to retire, and what annual income would you want?**" | **NEW: `capture_retirement_goals`** | `retirement_profiles` (target_retirement_age, target_retirement_income) | Skip if both set |
| 11 | `campaign_spouse_*` (reuse) + `campaign2_spouse_pensions` | spouse pension pots (existing users have contributions only) | `create_pension` / `capture_spouse_household_data` (exist) | spouse `dc_pensions` / household inputs | Skip if not married; sub-skip fields already held |
| 12 | `base_expenditure` (reuse) | monthly spending | free-text parse (exists) | `users.monthly_expenditure` | Skip if set |

### 5a. Why each question earns its place (the minimal-questions audit)

Every question maps to specific advice the engine voices back — the SaveTax discipline. If the advice can't fire, the question doesn't get asked (that's what the gates do).

| Question | The advice it unlocks |
|---|---|
| Employment + income | Readiness (BLOCKING); employer-match sizing, salary-sacrifice National Insurance saving, Annual Allowance headroom, higher-rate tax relief — all the %-of-salary maths |
| Date of birth | Readiness (BLOCKING); years to retirement, pension access age, projection horizon |
| Workplace pension (%, match, sacrifice) | "You're leaving free employer money on the table" (`employee_contribution_percent_below`), "switch to salary sacrifice" (`workplace_pension_no_salary_sacrifice`), auto-enrolment minimum check |
| Pot values | The pot projection and the headline income-gap number; consolidation advice (`multiple_dc_pensions`) |
| Personal/SIPP pots | Same, plus unclaimed higher-rate relief on personal contributions |
| Defined Benefit pensions | The guaranteed-income leg — without it the income gap is overstated and "increase contributions" advice could be flat wrong |
| State Pension forecast + NI years | The third income leg; voluntary NI top-up advice (`ni_years_wont_reach_required_by_spa`) — pound-for-pound the best-value action in the book |
| Contribution history (3 years) | Carry-forward allowance — **gated to higher-rate earners**; below that nobody out-contributes the standard £60k allowance |
| Flexible access | Money Purchase Annual Allowance £10k trap warning — **gated to age 55+**; impossible below pension access age |
| Target retirement age + income | The campaign's centrepiece: "you're on track / £X a year short", retire-later option, contribution-increase sizing |
| Spouse pensions | Household retirement income; survivor planning (if married only) |
| Expenditure | The affordability check behind "increase contributions by £X/month" — advice must never suggest money the user doesn't have |

**Deliberately NOT asked (Tier-4 engine fields whose advice value doesn't justify a chat question in v1):** member numbers, beneficiary nominations (estate territory), individual fund holdings/asset allocation, platform + advisor fees. The fee triggers simply won't fire without data — fine for v1; the module UI captures them later if the user goes deeper.

Verify config (`campaignVerifyConfig()`): pensions/state-pension/goals sections → `/retirement` (/m `Retirement.vue` already displays DC/DB/State Pension lists, pot projection, target age — the verify screen exists); income → `/income`; expenditure → `/expenditure`. The verify sub-flow is generic — zero changes.

Per-section advice: `SECTION_STRATEGY_TYPES` → retirement strategy types. The engine already emits 19 trigger-driven strategies (`RetirementActionDefinitionService:38–199`: employer match, salary sacrifice, contribution gap, NI gap-fill, consolidation, fees, later-retirement, decumulation review…). **No engine work.**

New capture tools (corpus `.md` + `.xai.md`, golden-master re-record, F15 lockstep):
1. `capture_retirement_goals` — target_retirement_age + target_retirement_income → `retirement_profiles`.
2. `capture_state_pension` — forecast_annual + ni_years_completed (+ state_pension_age optional) → `state_pensions`.
Everything else reuses existing tools. Schema descriptions govern model behaviour (`reference_tool_schema_description_governs_llm_defaults`) — write them carefully.

## 6. New-user funnel + plan page

Copy-and-reskin the savetax pair (blueprint seam). Sketch (grounded against engine needs, not marketing-final):

| Q | Key | Values | Drives |
|---|---|---|---|
| 1 | `employment` | as savetax | workplace-scheme state gating |
| 2 | `income` | as savetax bands | estimate + recap |
| 3 | `age` | banded (e.g. under-30 / 30s / 40s / 50s / 60+) | estimate horizon; flexible-access gate |
| 4 | `pensions` | multi: `workplace`, `personal_sipp`, `final_salary`, `none` | section/state gating (DB state, pots) |
| 5 | `pot` | banded pot value | estimate |
| 6 | `spouse` | yes/no | spouse section gating |

`funnel_answers.campaign = 'pension'` stamped at the funnel page (generalisation G2 — also fixes the lost-`from=` /m fallback for good). Plan page needs a new `PensionEstimateService` (SaveTaxEstimateService analog; banded, deterministic, every value via TaxConfigService). **Headline metric (D2, decided): projected pot at retirement** — "on track for a pension pot of roughly £X by age Y", from age/pot/contribution bands.

## 7. Landing (D4, decided): the existing `/retirement` module screen

Terminal `navigate_to` → `/retirement` (web and /m — the /m `Retirement.vue` already shows pensions list, pot projection, income vs target and years-to-retirement). No composed-plan landing page is built for v1.

**Knock-on (recorded so nobody "fixes" it later):** the synthesis (`buildSynthesisAdvice`) mirrors the composed plan items (F4). With no plan page, the composed retirement plan surfaces only through the **actions** surfaces (dashboard/`/actions` — WP-2 one actions model) — the synthesis bullets therefore mirror what appears in the user's actions list, not a landing page. The composed retirement plan is still computed (`ComposedModulePlanService::forSource()`, module-agnostic, RetirementStrategySource exists) — it just has no dedicated page. `applyCampaignAffinity` (`NextActionsService:227`) generalised to prefer `retirement` for pension-campaign existing users (G6) is what makes the landing feel campaign-shaped. A `/retirement-plan` composed landing stays on the shelf as a future enhancement (playbook §8 structural gap remains open by choice).

## 8. Generalisation pre-work (playbook §6 → this campaign)

| # | Savetax-ism | Fix here |
|---|---|---|
| G1 | Entry start-state hardwired `base_work` | campaign_map value becomes `['selection' => ..., 'entry' => ...]`; pension entry = existing-user recap / income |
| G2 | Lost-`from=` fallback assumes savetax | stamp `funnel_answers.campaign` at both funnel pages; read back in start |
| G3 | Campaign state names are savetax's | new `campaign2_*` states + reused sections (§5) — reuse-with-different-order confirmed sufficient |
| G4 | Terminal hardwired `/tax-strategy` | per-campaign `navigate_to` (§7) |
| G5 | Intro/recap builders assume savetax funnel shape | branch on `funnel_answers.campaign`; existing-user recap builder for re-entry (§5 state 0) |
| G6 | `applyCampaignAffinity` hardwires `tax` | parameterise by campaign module |

Plus the re-entry mechanism (§4) — the seventh generalisation, and the only one touching the canonical contract.

## 9. Decisions (CSJ, 2026-07-03 — LOCKED)

- **D1 — Re-entry mechanism: option (a) minimal** (§4). `users.active_campaign` + dispatch OR-branch; `onboarding_completed` never touched; `00-canonical.md` amended as part of the build.
- **D2 — Headline metric: projected pot at retirement.** Campaign name/URL still open (blueprint placeholder `biggerpension` is NOT decided — ask CSJ at funnel-build time).
- **D3 — Walk scope: pension-lean.** Savings/investments sections excluded from the pension walk.
- **D4 — Landing: existing `/retirement` screen.** No composed landing page in v1 (see §7 knock-on).

## 10. Build inventory (once §9 is decided — per playbook §7 checklist)

1. `users.active_campaign` migration + dispatch OR-branch + start re-entry + exits (§4) + `00-canonical.md` amendment.
2. Funnel + plan pages + `PensionEstimateService` + routes (before catch-all) + `PreviewWriteInterceptor::EXCLUDED_ROUTES` if new auth-adjacent POSTs.
3. G1–G6 generalisations.
4. Section config: `CAMPAIGN2_SECTION_ORDER`, `campaignSections()` skips, `campaignVerifyConfig()` routes.
5. New states (§5) — corpus workflow `.md` + `inCodeStates()` in lockstep (golden master); F1–F15 compliance.
6. New tools: `capture_retirement_goals`, `capture_state_pension` (+ golden-master re-record).
7. `SECTION_STRATEGY_TYPES` → retirement strategy types.
8. Terminal `navigate_to` → `/retirement` (web + /m verify the screen renders the walk's captures — D4).
9. `/m` mixin `from` forwarding for re-entry deep-links.
10. Milestones: `pension_pot`, `retirement_on_track`, NI/state-pension flavours already shipped in the WP-5c catalogue — verify they mint on the walk; add campaign flavours only if a gap shows in E2E.
11. E2E: new-user walk (fresh /m user) AND existing-user delta walk (julycsj3@example.com, id 168 on csjones — a real user who completed SaveTax onboarding, with milestones/actions data) per `reference_m_verification_path`; gamification `point_awards` check per walk.

**Estimated shape:** the heavy lift is §4 (re-entry, canonical) + §5 states/tools. Everything else is config + copy. No engine work, no new gamification wiring, no new landing page (D4).
