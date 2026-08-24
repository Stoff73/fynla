# Campaign Playbook — the complete SaveTax template for future campaigns

*2026-07-03. The consolidated, end-to-end map of the SaveTax campaign — funnel, registration, Fyn onboarding (with the full formatting standard), verify screens, synthesis, landing, actions, gamification and nudges — written as the template for the next campaigns (retirement planning, investment, cash budgeting, savings, property portfolios, inheritance tax). Every claim is file:line-grounded against the dev tip + WP-5c stack.*

**Companion documents (detail lives there, the playbook stitches them):**
- `campaign-blueprint.md` — the onboarding/campaign seams + hardcoded savetax-isms (same folder)
- `savetax-recs-gamification-map.md` — recommendations → actions → gamification → nudges + copy-across checklist
- `wp5c-milestones-spec.md` — the milestone catalogue + nudge layer

---

## 1. The end-to-end flow

```
Ad / CTA
  → funnel questionnaire  public/pages/savetax.php + js/savetax.js
      5 questions: employment · income band · spouse? · spouse income · assets (multi)
      answers → localStorage('savetax_answers') → query params
  → plan/estimate page    public/pages/savetax-plan.php + js/savetax-plan-v4.js
      SaveTaxEstimateService::estimate() (all values via TaxConfigService, Rule 2)
      personalised £ figure + allowances grid + compact register card
  → POST /api/auth/register (+funnel_answers)
      PendingRegistration (funnel_answers survives abandoned verification,
      PendingRegistration::createOrUpdate :67-94) → verify code → users.funnel_answers
  → dashboard?openFyn=journey&from=savetax  (phones: /m iframe route; /m
      auto-opens Fyn for onboarding arrivals — onboardingChat.js :44-46)
  → POST /api/ai-chat/onboarding/start (from=savetax)
      AiChatController :649-700: campaign_map match (falls back to durable
      funnel_answers if from= was lost) → onboarding_fyn_path='campaign',
      entry state base_work (income-first)
  → the section walk      OnboardingStateMachine::CAMPAIGN_SECTION_ORDER :148-155
      income → savings → investments → pensions → spouse → expenditure
      each section skippable off funnel_answers (asset-gating :1502-1571)
  → per section: capture → ONE existing gate → verify announce → navigate to
      the /m screen (+nudge) → Continue/Edit pills → confirm → section advice
      (+ "added to your actions list") → next section
  → synthesis             buildSynthesisAdvice — mirrors composed_plan.items as
      bullets (user->refresh() first; byte-consistent with /tax-strategy)
  → terminal              "We've created your tax strategy, {first_name}."
      navigation SSE → /tax-strategy (web + /m views)
  → post-campaign         WP-6 affinity puts tax actions first on the dashboard;
      milestones + activity feed show the onboarding as completed work
```

One backend for web AND `/m` (`AiChatController::sendMessage` / `onboarding/start`) — every campaign is surface-agnostic by construction (Rule 19 for free).

## 2. The Fyn formatting standard (campaign voice rules)

These are the conventions every campaign's prompts and handlers MUST follow. All are live in SaveTax with the implementation reference.

| # | Rule | Implementation |
|---|---|---|
| F1 | **Questions in bold** when a prompt mixes question with prose — the question line is `**bold**`, everything else plain | prompts at OnboardingStateMachine :462, :470, :479, :496, :512, :1072… |
| F2 | **One SSE response, multiple logical turns**: ack + `onboarding_advance` + next prompt stream in ONE response with exactly ONE `done` (inner delegated `done`s are swallowed) | OnboardingChatDirector delegated/grouped paths; contract in `reference_mobile_campaign_onboarding_and_fyn_streaming` |
| F3 | **Frontends split bubbles on `onboarding_advance`** (and on `quick_replies` when the bubble has text); resume from DB renders identical bubbles | mobile dock Dashboard.vue cursor pattern; `BUBBLE_BREAK` (`\x1E`) marker OnboardingStateMachine :136 → split at OnboardingChatDirector :746-766 |
| F4 | **Point-form recaps**: multi-item advice/synthesis is `- ` bullets with a one-line lead-in and a closing "together worth roughly £X a year" — no numbering, no conflict notes, no locked-teases (PR #592) | buildSynthesisAdvice :1025-1061 |
| F5 | **Deterministic read-backs**: after any capture/edit, the confirmation names the value re-read from the DB ("Updated HSBC Savings Account — balance now £520."), persisted as its own transcript row — never the model's paraphrase | verifyEditReadBack + :2882-2888 |
| F6 | **Action-logged notice**: every voiced strategy ends "I've added this to your actions list to come back to later." (spouse variant :978) | OnboardingChatDirector :935, :978 |
| F7 | **Milestone acknowledgement**: a capture that crosses a milestone gets ONE plain sentence — "That's a milestone: you've opened your first ISA." — its own transcript row (WP-5c-iii) | OnboardingChatDirector capture_complete block + MilestoneCollector |
| F8 | **Confirm-back for ambiguous input**: DOB echoed in full form ("Your date of birth is 19th February 1982 — is that right?") before saving; short formats accepted (19/02/82, 19 Feb 82; century by 18–105 age window) | pending_dob_confirm :196-220; #594 item 5 |
| F9 | **Retry copy names the expected format** with an example ("for example 12 January 1985 or 12/01/85") | retry_text per state, e.g. :313, :488 |
| F10 | **Record cards, not prose lists**: created records close with ONE `capture_complete` event → the record-card bubble ("Saved to your records" / "Saved N records"); suppressed when nothing was written | :3798-3804 + buildCaptureCompleteSummary |
| F11 | **Verify navigation carries the nudge** "Tap the chat below to continue with Fyn"; the transcript persists across navigation (never a cleared box) | navigation SSE :720-731; onboardingChat.js loadTranscript |
| F12 | **Time estimate in the greeting** (3–5 min base + 1 min per asset beyond the first) | greeting builder (PR #565 era) |
| F13 | **Level-ups arrive as a `level_up` SSE frame after `done`** → the celebration modal; Fyn's text itself never mentions points (Rule 12) | AiChatController::levelUpFrame :150-166 |
| F14 | **House rules apply to every word**: no icons/emoji ever (Rule 15 — Fyn is plain text), no acronyms except ISA (Rule 9 — "Annual Allowance", "Stocks & Shares"), no scores (Rule 12), British spelling, tax values only via TaxConfigService (Rule 2) | CLAUDE.md |
| F15 | **Prompt data lives in two places in lockstep**: the corpus workflow `.md` (authoritative for static prompt/retry/bubbles) + `inCodeStates()`; `OnboardingWorkflowTableGoldenMasterTest` enforces parity. Tool behaviour is governed by the live `.xai.md` schema DESCRIPTIONS (change the description → re-record golden master) | blueprint seam rows; `reference_tool_schema_description_governs_llm_defaults` |

## 3. The canonical verify sequence (CSJ-fixed 2026-06-17 — do not re-complicate)

Per section: **capture → the ONE existing gate** (e.g. income's "any other roles or sources of earned income?" — never a second "anything else?") **→ No → announce → navigate to the section screen with the nudge → the screen shows what was entered → user reopens chat (full transcript) → "Yes, that's right" → THEN the section advice → next section.** Advice always AFTER the confirm. "No, change something" → `campaign_verify_edit` (update-only, honesty-gated) → read-back (F5) → back to the confirm. The old `campaign_verify_more` state is orphaned — kept only for golden-master parity.

## 4. Screens inventory

| Surface | Screen | Role |
|---|---|---|
| Public | `savetax.php` → `savetax-plan.php` | funnel questions → estimate + register card |
| SPA | `Register.vue` | verification; `funnelHandoff` hold panel (no form flash, #594); `from=` → dashboard with Fyn open |
| /m verify | `/income` `/savings` `/investment` `/retirement` `/expenditure` (resources/mobile/views/) | the navigate-to-confirm screens; each lists the captured records (income shows employer · role · amount) |
| /m terminal | `TaxStrategy.vue` (`/tax-strategy`) | allowance grids (+spouse in household modes), composed plan with completion state |
| /m dashboard | `Dashboard.vue` | level wheel + hero milestone nudge (WP-5c-iii), campaign-affinity top actions (WP-6), milestone toast + share |
| /m progress | `Achievements.vue` | badges · Done (paginated) · Milestones (grouped Next up, deep-linked) · History (infinite scroll) |
| Web | same backend; desktop `/actions`, `/tax-strategy` | one actions model (WP-2); NO desktop achievements/milestones/history yet (deferred, spec §6) |

## 5. Parameterisation seams (what a new campaign changes)

The blueprint's seam table (campaign-blueprint.md) holds the detail; consolidated list with the WP-5c additions:

1. **Entry routing** — `config/onboarding.php` `campaign_map` (+ entry state once generalised — see §6.1).
2. **Funnel + plan pages** — copy the savetax pair + JS, reskin, stamp `funnel_answers.campaign`.
3. **Section order/subset + skip predicates + verify routes** — `CAMPAIGN_SECTION_ORDER`, `campaignSections()`, `campaignVerifyConfig()`.
4. **States + prompts** — corpus workflow `.md` + `inCodeStates()` in lockstep (F15), following the F-rules.
5. **Per-section advice** — `SECTION_STRATEGY_TYPES` → the campaign module's strategies. **All six module strategy sources already exist** (`PlanSources/`: Retirement, Investment, Savings, Protection, Estate, Tax) — the composed-plan engine (sequencing, conflicts, locked-strategy unlocks, stable ids, completion stamping) is module-agnostic and already built.
6. **Terminal destination** — per-campaign landing page (web AND /m — Rule 19).
7. **Dashboard affinity** — `NextActionsService::applyCampaignAffinity()` (:227) currently prefers `tax`; generalise to the campaign's module.
8. **Milestones** — add campaign flavours (one detect method + thresholds via `recordOnce`; labels in `milestoneTitle` + the feed map; upcoming group + route). The engine (collector, push flag, deep-links, hero nudge, Fyn ack) is campaign-agnostic.
9. **Gamification** — automatic (state-id awards, data-entry trait, recommendation completion, milestone points).

## 6. Hardcoded savetax-isms to generalise before campaign #2 (from the blueprint — still true)

1. Entry start-state is hardwired to `base_work` (income-first is a savetax decision) — move into the campaign_map value.
2. Lost-`from=` fallback assumes savetax — stamp `funnel_answers.campaign` at the funnel page.
3. Campaign state names/prompts are savetax's (reuse sections with a different order/subset is likely sufficient for v1).
4. Terminal destination `/tax-strategy` — per-campaign route.
5. Intro/recap builders assume the savetax funnel_answers shape.
6. (WP-6) `applyCampaignAffinity` hardwires module `tax`.

**Plus the standing design decision (deliberately not built):** existing users who completed SaveTax onboarding have `onboarding_completed = true` and can never re-enter the campaign state machine — campaign #2 for existing users needs either a lightweight re-entry mode or an advice-surface prompt overlay (blueprint §existing-users). CSJ's call before campaign #2.

## 7. Per-campaign build checklist

Blueprint steps 1–9 (config key · funnel pages · web routes · section config · strategy types · corpus+table lockstep · terminal + /m view · PreviewWriteInterceptor · E2E walk) **plus**:

10. Extend `applyCampaignAffinity` to the campaign module (one function).
11. Add campaign milestone flavours + labels + upcoming group/route (spec §3 pattern).
12. WP-6-equivalent landing state (graduates land on the campaign module's actions).
13. Verify the full formatting standard (§2 F1–F15) on the live walk.
14. Gamification check on the walk user: point_awards ledger, level, milestones minted, activity feed labels.

## 8. Fit notes for the named future campaigns

| Campaign | Engine status | Funnel questions (sketch) | Landing | Notes |
|---|---|---|---|---|
| Retirement planning | `RetirementStrategySource` exists; RetirementProjection/AnnualAllowance services live | age, target retirement age, pension types held, current pot band, contribution level | retirement module (needs a plan-style landing like /tax-strategy) | pension_pot + retirement_on_track milestones already shipped |
| Investment | `InvestmentStrategySource` exists | assets held, ISA usage, risk appetite, horizon | investment module | ISA allowance milestones already shipped |
| Savings / cash budgeting | `SavingsStrategySource` exists; EmergencyFundCalculator live | income band, monthly spend band, savings held, rate awareness | savings module | emergency-fund milestones already shipped; budgeting needs expenditure-first section order |
| Property portfolios | property has no StrategySource yet — engine work needed | properties held, mortgages, rental income, ownership structure | net-worth/property | mortgage-paydown milestones already shipped |
| Inheritance tax | `EstateStrategySource` exists; IHT calc + gifting strategy services live | age band, estate value band, will?, gifts made, married? | estate module | will/Lasting Power of Attorney/estate milestones already shipped |

The one structural gap across all of them: none has a `/tax-strategy`-style composed-plan landing page — the composer is module-agnostic, so each campaign's landing is a re-skin of the TaxStrategy view over its module's `ComposedModulePlanService::forSource()` output.
