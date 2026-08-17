# Pension Campaign (/pensioncheck) — Technical Patch Notes

*2026-07-04. Covers PRs #607, #608, #609 (the three build slices, planned 2026-07-03) and #610 (the live-verification fix wave). All merged to `dev` (`6f965f1`) and deployed to csjones, live-verified both user classes. Prod untouched. Design source: `July/July3Updates/pension-campaign-map.md`; execution plan: `pension-campaign-plan.md`.*

## Summary

Campaign #2 — pension planning at `/pensioncheck` — cloned from the SaveTax template per the campaign playbook, with one new architectural capability: **campaign re-entry for completed users**. New users run a pension-lean onboarding walk; existing users (completed SaveTax onboarding) re-enter, get a recap of held data, and answer only the missing-data questions. Final suite: **5,490 passed / 30 expected skips**.

## PR #607 — re-entry substrate + seam generalisations (Slice A)

**Canonical contract change** (`00-canonical.md` amended, mirrored in CLAUDE.md): a user with `onboarding_completed = true`, non-null `users.active_campaign`, and non-null `onboarding_fyn_step` routes to the onboarding write state for the duration of a campaign walk. `onboarding_completed` is never written by re-entry.

- `users.active_campaign` (nullable string(32)) — migration `2026_07_03_000001`.
- Dispatch predicate centralised in `AiChatController::routesToOnboardingDirector()` and shared by all THREE seams: `sendMessage`, `streamQueuedMessage`, `action` (execution finding: the predicate was duplicated at three sites; the plan had covered one).
- `POST /api/ai-chat/onboarding/start`: the flat 409 for completed users becomes conditional — a `from=` resolving to a `campaign_map` entry with `reentry => true` stamps `active_campaign` + entry step and streams the campaign intro. Flow-flag 503 and preview 403 gates still apply to re-entry. Resume branch serves mid-campaign re-entrants.
- Exits: campaign terminal and the "Something else" pause both clear `active_campaign` unconditionally; completion side effects (completed flag, `completed_at`, `recordProgress`) are guarded by the pre-terminal completed value so re-entry never double-fires them.
- `campaign_map` values generalised from bare strings to `['selection','entry','reentry','reentry_entry']`.
- `funnel_answers.campaign` stamped at the funnel JS (savetax too); the lost-`from=` fallback reads it (string-guarded, legacy rows default savetax). `RegisterRequest` gained `funnel_answers.campaign` validation.
- `/m` onboarding mixin forwards `from` (was hardcoded `{}`).
- `NextActionsService::applyCampaignAffinity` parameterised (`savetax → tax`, `pensioncheck → retirement`); legacy non-stamped funnel rows keep the savetax→tax boost (regression caught in review: the first cut dropped it and adapted the characterisation test — reverted to a code fix).

## PR #608 — public surfaces (Slice B)

- `app/Services/Marketing/PensionEstimateService` — banded deterministic projected-pot estimate (band midpoints as marketing constants; tax-derived figures via TaxConfigService only; review-caught Critical: the 60% taper note initially cited the higher-rate threshold as the taper start — now `income_tax.personal_allowance_taper_threshold`).
- `public/pages/pensioncheck.php` + `pensioncheck-plan.php` (+ JS): six questions (`employment`, `income`, `age`, `pensions` multi, `pot`, `spouse`), answers to `localStorage('pensioncheck_answers')` with `campaign: 'pensioncheck'`; plan page injects `window.PENSIONCHECK_ESTIMATE` server-side (hex-flagged JSON; query values never echoed — XSS-clean); v4-pattern inline register card POSTs `funnel_answers`; "Already with Fynla? Log in" carries `from=pensioncheck`.
- Routes mirror the savetax include pattern, before the SPA catch-all; `RebasePublicPageUrls` is global web middleware (Kernel:92) so FYNLA_BASE rewriting is automatic.
- Homepage: `feature-pensioncheck` card beside the savetax card, server-computed representative pot, exact savetax visual pattern (a "distinguishing" gradient was reverted per Rule 16), failure-tolerant (service throw → card renders without a figure).

## PR #609 — the walk (Slice C)

- **Per-campaign section machinery (G3):** `sectionOrderFor()/campaignSections()/campaignVerifyConfig()` keyed by campaign; savetax arrays byte-identical (characterisation-pinned). Pensioncheck order: income → pensions → state_pension → retirement_goals → spouse → expenditure.
- **Data-presence skips (the existing-user delta):** income keyed on captured income columns (review-caught Critical: `employment_status` is registration-seeded and would have skipped income for every NEW registrant), State Pension row-existence, goals both-fields, expenditure `> 0`.
- **Nine states** in `inCodeStates()` + corpus lockstep: `campaign2_existing_recap` (dynamic recap), `campaign2_pension_pots` (pot backfill via `update_record`, `current_fund_value <= 0` sentinel — a `whereNull` dead-query was caught), `campaign2_pension_db` (create_pension covers Defined Benefit), `campaign_pension_history` restored pensioncheck-only with a TaxConfigService higher-rate gate (savetax keeps its June #586 removal), `campaign2_flexible_access` (55+ only), `campaign2_state_pension`, `campaign2_retirement_goals` (with cross-turn income parking via `onboarding_parked_facts`), `campaign2_spouse_pensions`, `campaign2_terminal` (→ `/retirement`). Walk routes THROUGH `campaign_synthesis` before terminal (review-caught Critical: an earlier cut bypassed synthesis).
- **Two new capture tools** (dual-provider schemas + golden masters; both in `AdviceFyn::WRITE_TOOLS`): `capture_retirement_goals` (retirement_profiles; income-only answers use the `details.missing` partial-retry protocol — review-caught F5 silent drop), `capture_state_pension` (state_pensions).
- **Advice + synthesis:** `PENSIONCHECK_SECTION_STRATEGY_TYPES` maps sections to real retirement strategy ids (`increase_pension_contribution`, `salary_sacrifice_pension`, `carry_forward_unused_allowance`, `plan_retirement_income`); non-mapped sections are silent — no pensioncheck path reaches any tax builder (review-caught cross-campaign advice leak). `buildSynthesisAdvice` campaign-aware: pensioncheck mirrors the composed retirement plan as F4 bullets; savetax byte-identical (PR #592 pin strengthened).
- **Config ON:** `campaign_map` gains `'pensioncheck' => ['selection','entry'=>'base_work','reentry'=>true,'reentry_entry'=>'campaign2_existing_recap']`.
- **StoreBoundary:** pension reads via `PensionStore` (6 narrow readers added) — architecture suite enforced.

## PR #610 — live-verification fix wave (Slice D findings)

The scripted-client suite (5,461 green at merge time) could not catch these; the live csjones walks did. Five rounds, each live-re-verified:

1. **Root cause of all D1 capture failures:** `OnboardingPromptBuilder::toolsForFocus()` had no `pensioncheck` arm — delegated pension turns fell to the savings default (`create_savings_account` only), so the live model security-refused pension answers and hallucinated acks. Added the pensioncheck catalogue arm (create_pension, update_record, capture_pension_history).
2. Retirement-analysis `remember()` cache never invalidated by the two new capture handlers (tag invalidation silently no-ops on the file cache driver) → empty synthesis; both handlers now call `invalidateUserCache`. `handleCaptureRetirementGoals` also syncs `users.target_retirement_age` (the projection/readiness layer reads the users column, not the profile row).
3. Web router lacked a top-level `/retirement` route (only /m defines it) → the terminal CTA hit the NotFound catch-all. Added `/retirement → /net-worth/retirement` redirect. `capture_state_pension` hardened: writes only on `forecast_annual > 0 OR ni_years > 0` (the model invented `state_pension_age=60` from an NHS answer). Verify-navigate state-id chat leak fixed (was leaking on savetax too). Synthesis degrades gracefully on an empty composed plan (was silent — also a savetax bug).
4. `UpdateRecordAllowlist` for dc_pension gained `monthly_contribution_amount`-adjacent fields + `has_flexibly_accessed` (closing the flagged latent flexible-access gap). F5 honesty: a blocked/failed write can no longer produce a "Recorded" ack — the ack layer keys on an observed landed write.
5. `campaign_pension_contribs` carries the existing-pension reference context (`record_context_mode='contribution'`) so a contribution against an existing personal pension goes via `update_record` instead of a duplicate-blocked `create_pension`. Gated on personal pensions being on file — savetax byte-identical.

## E2E verification (csjones, real xAI model, Playwright)

- **D1 fresh-user walk (GREEN):** homepage CTA → funnel → £282,751 personalised estimate → registration → full walk → 4-bullet synthesis → terminal → `/retirement` with all captures rendering. DB-verified: dc/db/retirement_profiles rows, funnel keys, no garbage State Pension row from "not sure", 16 point awards.
- **D2 existing-user delta walk (GREEN):** julycsj3 re-entry → Welcome-back recap (income + pension bullets) → exactly 7 gap questions, zero re-asks (income/DOB/expenditure) → synthesis → terminal → advice mode answers normally afterwards.
- **D3 integrity (GREEN):** `onboarding_completed_at` byte-identical through two re-entries; terminal award count stayed 1; `active_campaign` cleared; 4 milestones minted.
- **D4 savetax regression walk (GREEN):** zero campaign bleed; June behaviours intact (no carry-forward, #581 savings scoping); synthesis degrade-line on a zero-item plan is the new graceful behaviour.
- **D5 targeted contribution fix (GREEN):** "£200 a month" landed as `monthly_contribution_amount = 200.00` on the existing pension, truthful ack, no duplicate row.

## Deploy state

- dev = csjones = `6f965f1` (merge of #610). Corpus validators exit 0. Bundle manifest hash-verified both sides. DB reseeded post-migration. Prod untouched — the dev→prod release window now spans #581–#610.
- Test users cleaned (7 users, zero orphans); `julycsj3@example.com` (id 168) retained as the standing /m test user, now with pension data.

## Known items / deferred (CSJ-owned)

1. All campaign copy is DRAFT (funnel, plan page, homepage card, Fyn walk lines); OG images `og/pensioncheck*.jpg` referenced but not created.
2. Carry-forward question re-included for pensioncheck (higher-rate-gated) despite the June #586 savetax removal — needs conscious blessing.
3. Post-terminal campaign affinity reverts to tax-first for re-entrants (terminal clears the selection) — needs a durable signal if retirement-first should persist.
4. Pension access age 55 hardcoded in two places (rises to 57 in April 2028) — should move to TaxConfigService with effective-from.
5. Cosmetics: verify-bubble prompts render literal `**` on web (pre-existing, shared with savetax); "I've saved your bank accounts" label on a savings-only savetax verify (pre-existing); retirement page derives monthly contribution from salary so %-only pensions display £0.
6. Deferred by design (map): no `/retirement-plan` composed landing page; desktop achievements/milestones parity; milestone email loop; fees/beneficiary/member-number questions.
