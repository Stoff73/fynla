# Campaign Blueprint — how the SaveTax campaign works, and what to copy for the next one

*Written 2026-07-03 after the SaveTax issues.md fixes (branch `savetax-campaign-fixes`). This is the map for cloning the flow into a Protection / Pensions / Investment campaign. No second campaign is built yet — this documents the seams.*

## The shape of a campaign

```
Ad / CTA → funnel (public PHP, 4 questions) → plan page (register card)
  → POST /api/auth/register (+funnel_answers) → PendingRegistration → verify code
  → Register.vue completes → dashboard?openFyn=journey&from=<campaign>
  → POST /api/ai-chat/onboarding/start (from=<campaign>)
  → campaign_map match → onboarding_fyn_path='campaign', section-led walk
  → per-section: capture → ONE gate → verify announce → navigate to section screen
     → on-page Continue/Edit pills → confirm → section advice (+ actions line)
  → synthesis (mirrors the composed plan) → terminal → destination page
```

Backend is one endpoint for web AND /m (`AiChatController::sendMessage` / `onboarding/start`) — the campaign is surface-agnostic by construction (Rule 19 for free).

## The seams (what is already parameterised)

| Seam | File | What a new campaign changes |
|---|---|---|
| Entry routing | `config/onboarding.php` → `campaign_map` | Add `'biggerpension' => 'biggerpension'`. The controller comment is accurate: "Adding a new campaign requires only a new key/value pair here" — for the ROUTING. |
| Section order | `OnboardingStateMachine::CAMPAIGN_SECTION_ORDER` | Reorder / subset the section walk. "To reorder the journey, reorder this array; nothing else needs to change." A pension campaign leads with `pensions`, not `income`… but see gap 2. |
| Section entries + skips | `OnboardingStateMachine::campaignSections()` | Entry state per section + whole-section skip predicates keyed off `funnel_answers`. |
| Verify destinations | `OnboardingStateMachine::campaignVerifyConfig()` | Section → /m screen route for the verify-navigate. The verify sub-flow (announce → navigate → confirm → edit) is fully generic — it reads `verify_section` from context. |
| Per-section advice | `OnboardingChatDirector::SECTION_STRATEGY_TYPES` | Which composed-plan strategy types are voiced per section. Advice text comes from the engine (Rule 2); each voiced section ends with the "added to your actions list" line. |
| Synthesis | `buildSynthesisAdvice` | Mirrors `composed_plan.items` — campaign-agnostic already (it voices whatever the engine composed). |
| State prompts (DATA) | `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` + `inCodeStates()` | **Both, in lockstep** — the corpus is authoritative for static prompt_text/retry_text/bubbles; `OnboardingWorkflowTableGoldenMasterTest` enforces parity. Bold the question line when a prompt mixes question + prose. |
| Capture tools | `fyn-memory/procedural/tool_schema/onboarding/*.md` | Live schema descriptions govern model behaviour (see the DOB short-format fix). Changing one requires `CAPTURE_TOOL_SCHEMA_GOLDEN=1 pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php` to re-record fixtures. |
| Funnel + plan pages | `public/pages/savetax*.php` + `js/savetax-v2.js`, `js/savetax-plan-v4.js` | Copy-and-reskin per campaign. `funnel_answers` shape (employment / income band / spouse / assets) is persisted through `PendingRegistration` → `users.funnel_answers` and drives skip rules + the income cross-check challenge. |
| Registration handoff | `Register.vue` (`from=` query → campaign dispatch; `funnelHandoff` hold panel) | Generic: any `from=<id>` routes to the dashboard with Fyn open; the campaign/journey resolution is server-side. |
| Gamification | automatic | `recordProgress` awards per state id; data-entry trait awards on created records; no campaign-specific wiring needed. |

## The hardcoded savetax-isms (what a second campaign MUST generalise)

1. **`AiChatController` entry start-state** (`onboarding/start`, ~line 683): a matched campaign always starts at `STATE_BASE_WORK` (income-first is a savetax decision). Generalise: put the entry state in the campaign_map value (e.g. `'savetax' => ['selection' => 'savetax', 'entry' => STATE_BASE_WORK]`).
2. **Funnel-answers fallback** (same block): keys `campaignMap['savetax']` by name — a user whose `from=` was lost across the /m iframe handoff is assumed savetax. With two campaigns, stamp the campaign id INTO `funnel_answers` (e.g. `funnel_answers.campaign = 'biggerpension'`) at the funnel page and read it back here.
3. **Campaign state names + prompts** (`campaign_isa_holdings` … `campaign_terminal`): the walk mechanics are generic but the states are savetax's. A pension campaign adds its own `campaign2_*` states (or reuses these sections with a different order/subset — likely sufficient for v1).
4. **Terminal destination**: `campaign_terminal.navigate_to = '/tax-strategy'`. Per-campaign terminal route needed.
5. **The intro/recap builders** (`buildCampaignIntroPrompt`, the funnel recap in `buildPersonalPrompt`/`buildFunnelRecapPrompt` path) assume the savetax funnel_answers shape.

## Existing users (already onboarded via SaveTax) entering a future campaign

The onboarding write state requires `onboarding_completed === false` AND `onboarding_fyn_step !== null` (canonical 3-part dispatch — `00-canonical.md`). A user who finished SaveTax onboarding has `onboarding_completed = true`, so they can NEVER re-enter the campaign state machine as-is; their writes flow through Advice Fyn → `delegate_to_capture` → `handleInlineCapture`.

**Design decision needed before campaign #2** (deliberately NOT built now): either
- (a) a "campaign re-entry" that sets a campaign context without touching `onboarding_completed` (a parallel lightweight director mode), or
- (b) drive existing-user campaigns through the advice surface + inline capture with a campaign-specific prompt overlay (no state machine).
The section/verify machinery is reusable either way because it is keyed off `onboarding_fyn_context`, not off the campaign name.

## Per-campaign copy checklist (when CSJ green-lights campaign #2)

1. `config/onboarding.php` — add the campaign key (+ entry state once seam 1 is generalised).
2. Funnel + plan PHP pages + JS (copy savetax pair, reskin, stamp `funnel_answers.campaign`).
3. Routes in `routes/web.php` (mirror the `/savetax` block, declared before the catch-all).
4. Section order/subset + verify config + skip rules in `OnboardingStateMachine`.
5. `SECTION_STRATEGY_TYPES` for the campaign's advice sections (engine strategies must exist).
6. Corpus workflow `.md` + in-code table in lockstep (golden master enforces).
7. Terminal state + destination page (web AND `/m` view — Rule 19).
8. `PreviewWriteInterceptor::EXCLUDED_ROUTES` if any new auth-adjacent POST routes are added.
9. E2E walk on /m per `reference_m_verification_path` + gamification `point_awards` check.
