# PRD — Fyn Persona Split + Onboarding UX Overhaul

**Project:** Fyn Persona Split + Onboarding UX Overhaul
**Owner:** CSJ
**Status:** Draft — amended 2026-04-22 (session 3) for profile-review pause routing + two-width chat normalisation
**Date:** 21 April 2026 (orig); 22 April 2026 (amendment)
**Spec:** `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md` (amended 2026-04-21 and 2026-04-22)
**Plan:** `April/April21Updates/plan-fyn-persona-split.md` (amendments A–L — see the plan's AMENDMENTS section, with §L covering the 2026-04-22 route-push change)
**Codebase audit:** Completed 2026-04-21 — all 19 findings resolved in amended spec/plan. See Risks & Dependencies §Residual concerns for the two deferred items.
**Target branch:** `feature/fyn-persona-split` off `onboardingFyn`

---

## 1. Context & Why

### Problem

Fyn (Fynla's conversational AI) runs a single ~1,600-token advice system prompt (`App\Services\AI\SystemPromptBuilder`) for every chat turn, regardless of whether the user is asking for advice or giving Fyn data to store. This has three concrete consequences:

1. **Behaviour quality during data entry is brittle.** The advice prompt's FCA 6-step process instructions bias the LLM toward single-tool-per-turn emission, which breaks multi-entity capture ("add my Nationwide ISA at £5k, Halifax at £12k, and Santander at £8k" currently captures one and forgets the other two). FR-M14 shipped a buffered sentence-level off-script filter in `OnboardingChatDirector::handleAssetCaptureTurn` plus a streaming-duplicate fix in `resources/js/store/modules/aiChat.js` to suppress leaks — and further whack-a-mole is likely as the same root cause keeps surfacing.
2. **Tokens are wasted.** Data-capture turns pay the full advice tax — financial context, existing records, data completeness, query knowledge, KYC gate — even though none of that context is relevant to a request like "add my ISA".
3. **The architecture does not scale.** Adding a third persona (advisor mode, post-retirement mode, advisor-product mode) would require duplicating large chunks of `SystemPromptBuilder` or layering more flags. There is no clean seam for persona introduction.

On the onboarding side specifically, four further pain points have accumulated during FR-M9..FR-M15:

4. **Fyn silently ignores extra information.** When a user answers Fyn's current question and volunteers more than asked ("married to Angela, 45"), Fyn captures only the current question's answer and drops the rest — then asks about the dropped content a turn later. This makes Fyn feel forgetful.
5. **Onboarding Fyn re-asks what's already known.** Even when the user has volunteered spouse/dependant/employment info in an early free-text message, subsequent state handlers still ask the canonical question rather than consulting memory.
6. **Resume-from-where-left-off is broken.** The "welcome back" flow was planned, claimed as shipped, but does not fire in practice. Users who log out mid-onboarding and return see a fresh first turn rather than a resume prompt.
7. **Onboarding UX lacks visual affordances.** The chat window is the same size whether the user is in an active capture block or at a logical checkpoint. The dashboard behind is always visible and distracting. There's no spouse-skip affordance, no multi-job capture, and `Employment → Other` is an answer that nobody picks but everybody sees.

### Business case

Fynla's AI is the moat — users trust Fyn to capture their financial life accurately and advise on it usefully. The token cost on wasted advice-prompt context is recurring COGS; the behaviour bugs cost engineering time to fix and user trust to keep; the onboarding drop-off happens where users encounter the "forgetful" and "re-asking" problems. Fixing these three classes of problem at once — with a clean architecture that scales to the advisor product next quarter — compounds in every future AI feature.

Concrete immediate wins:
- Data-capture turns drop from ~1,600 tokens to ~500 tokens of system prompt (measurable Anthropic / xAI spend reduction).
- Onboarding completion rate should improve as users stop hitting the "forgetful Fyn" and "no resume" friction points.
- The advisor product (next quarter) can register as a third persona in the registry with zero orchestrator changes.

### Strategic fit

This change touches **every module** indirectly via the Fyn chat surface, and specifically the **Coordination** module (where the orchestrator lives) and **Estate Planning** (new Will / LPA AI tools). It directly builds on:

- `onboardingFyn` branch — FR-M9..FR-M15 just shipped; this release extends that work rather than redoing it.
- `CoordinatingAgent` + `HasAiChat` trait — the existing LLM-primitive layer that both the director and the new orchestrator depend on.
- `QueryClassifier` + `QuerySchemas::DATA_ENTRY` — reused (promoted to the orchestrator level) rather than duplicated.
- `fynlaDesignGuide.md v1.3.0` — wide-chat layout, dashboard blur, raspberry skip link, capturing-pill all conform.

It unblocks:
- Future advisor product (persona-3).
- Mobile Fyn quick-start (persona-4) — the registry pattern makes this cheap.
- Generalisation of the FR-M14 filter and retraction logic to any persona.

### Status against live code

Partially shipped — the `onboardingFyn` branch currently on dev (csjones.co/fynla) hosts commits `fd3ff44`, `039b258`, and `6211451` from the FR-M14 follow-up + journey remap session. The persona split and onboarding UX overhaul in this PRD are **not yet implemented**. Any code reference in this PRD is to the target state after implementation, not to the current branch HEAD.

---

## 2. Target Persona

**Primary — all Fynla users who interact with Fyn.**

The persona split is a universal AI-infrastructure change. Every seeded preview persona (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple) benefits because every Fyn chat turn flows through the new architecture. The onboarding UX overhaul targets the same universe of users.

The users who feel these problems most acutely are:

- **Young Family (James & Emily Carter)** — most likely to volunteer family info in early free-text ("wife Emily, two kids, mortgage"). Today's Fyn re-asks each field; the fact-parking feature eliminates that friction.
- **Peak Earners (David & Sarah Mitchell)** — most likely to have multiple jobs and complex employment. Today's flow captures one job; the multi-job loop captures all of them.
- **Retired Couple (Patricia & Harold Bennett)** — most likely to want to enter a will and LPAs. Today there are no AI tools for either; the new `create_will` and `create_power_of_attorney` tools close the gap.
- **Entrepreneur (Alex Chen)** — most likely to add data mid-advice ("oh, add my SEIS investment"). The post-onboarding inline-capture flow handles this cleanly via `delegate_to_capture`.

**Secondary:** advisors (future product) — benefit from the persona registry being extensible.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement window | Source |
|--------|----------|--------|---------------------|--------|
| Average system-prompt tokens on data-capture turns | ~1,600 | ≤ 600 | 7 days post-enable on prod | Anthropic / xAI usage API — tagged via log aggregation |
| FR-M14-style off-script leak complaints (support / user feedback) | 2–3/week during FR-M14 rollout | 0/month | 30 days post-enable | Support queue + `storage/logs/laravel.log` `off_script_filter_triggered` warnings |
| Onboarding drop-off rate between `base_spouse` and `asset_capture` | unknown — requires instrumentation | 20% reduction | 30 days post-enable | `users.onboarding_fyn_step` snapshot query before / after |
| Average number of questions asked during onboarding spouse block for users who volunteer spouse info early | ~3 | ≤ 1 (gap-fill only — usually just "email?") | 30 days post-enable | `AiMessage` count per conversation between `base_personal` end and `base_dependants` start, filtered to users whose first message contained spouse tokens |
| Resume-from-where-left-off invocation rate for users with `onboarding_completed = false` AND `onboarding_fyn_step != null` | 0% (flow is broken) | 100% of qualifying sessions see the welcome-back greeting | 14 days post-enable | `ai_messages.content` text match + action endpoint hit logs |
| Orchestrator handoff success rate (`delegate_to_capture` → `capture_complete` round-trip without timeout or malformed-args fallback) | N/A (new) | ≥ 98% | 30 days post-enable | `storage/logs/laravel.log` `FynPersonaOrchestrator` warning rate vs successful-completion rate |
| LPA creation via `create_power_of_attorney` tool (new capability) | 0 (no tool today) | ≥ 10 per 100 completed-onboarding users | 30 days post-enable | `lasting_powers_of_attorney` row count filtered by `created_at` + Fyn-authored source |

Measurement dependencies (flagged under §8 Technical dependencies):
- Token-accounting requires a log aggregation line in `FynPersonaInvoker::invoke()` that records `persona`, `prompt_length`, `tool_count` per turn. Already in the plan (see Task 13 Step 3 in the amended plan).
- Drop-off rate requires a snapshot query before the release; baseline row must be captured before `FYN_PERSONA_SPLIT` is flipped on prod.
- Off-script leak complaints require a simple `Log::warning('[OffScriptFilter] sentence-dropped', [...])` in the existing filter inside the director; the metric is a log-line count, not a new system.

---

## 4. User Stories & Scenarios

### User stories

**Onboarding (all persona seeds):**
- As a **new user mid-onboarding**, I want Fyn to remember what I've already told it so that I'm not asked the same question twice.
- As a **new user who gives Fyn extra info in one message**, I want Fyn to capture it all and only ask me for what's genuinely missing.
- As a **new user who logs out part-way through onboarding**, I want Fyn to greet me on return with where we left off and let me continue or start over.
- As a **married user**, I want to skip detailed spouse questions if I don't want to share right now, with a clear inline "skip this" option.
- As a **user with multiple jobs**, I want to add them all to Fyn's record during onboarding, not just the first one.
- As a **user reviewing my captured profile mid-flow**, I want the dashboard behind Fyn to fade into the background so I can focus on confirming what's correct.
- As a **user who misspoke earlier** ("I said single but actually I'm married"), I want to correct it in the chat and have Fyn update its records with a clear before→after confirmation.

**Post-onboarding advice (all persona seeds):**
- As a **user asking for retirement advice with no pensions loaded**, I want Fyn to note my pension details first, then answer my original question — without me having to re-ask after providing the data.
- As a **user mid-advice thread** who says *"oh, add my Nationwide ISA £5,000"*, I want Fyn to record it inline and continue the conversation without a jarring mode switch.
- As a **user who accidentally triggered a capture**, I want to cancel with a natural *"never mind"* and have Fyn drop back to advice mode.
- As a **user in preview mode**, I want Fyn to explain that data won't save and offer a sign-up CTA rather than silently failing a write.

**Estate planning (retired_couple, peak_earners):**
- As a **user with a will**, I want to record its executor, beneficiaries, and specific gifts via Fyn.
- As a **user with an LPA**, I want to record it via Fyn against the existing `LastingPowerOfAttorney` model so the Estate module's existing UI shows it.

### Key scenarios

**Scenario 1 — KYC-gated advice with seamless return.**

1. Post-onboarding user sends: *"What should I do about my pensions?"*
2. `AiChatController` routes to `FynPersonaOrchestrator` (flag on, user's onboarding_completed=true).
3. `QueryClassifier` runs — `primary=ADVICE`, not `DATA_ENTRY` — no fast-path. Orchestrator invokes advice persona.
4. `AdvicePromptBuilder::build()` produces the 10-layer advice prompt. `<financial_context>` and `<existing_records>` show no DC/DB pensions. Advice Fyn emits:
   - Text acknowledgment: *"Let me note your pension details first — then I can answer properly."*
   - `delegate_to_capture` tool call with `reason="retirement advice blocked on missing pension data"`, `entity_types=["dc_pension", "db_pension"]`.
5. Orchestrator intercepts the delegate (stripped from SSE), persists an `AiMessage(persona='advice')` with the acknowledgment, sets `persona_state.current='capturing'`, stores the original question in `persona_state.pending_advice_question`, and invokes data-capture Fyn.
6. `DataCapturePromptBuilder::build()` produces a ~500-token prompt. Data-capture Fyn asks: *"Could you tell me about each pension — provider, current value, and DC or DB?"*
7. User: *"Scottish Widows SIPP £50k, DC."*
8. Data-capture Fyn calls `create_pension`, then emits `capture_complete(summary="Added Scottish Widows SIPP £50k", records_created=[{type:'dc_pension', id:N}])`.
9. Orchestrator intercepts `capture_complete`, persists `AiMessage(persona='data_capture')`, reads `pending_advice_question`, resets state to `{current:'advice'}`, re-invokes advice persona with a system-injected suffix re-priming the original question plus the newly captured record.
10. Advice Fyn answers the original pension question with the new data loaded. User sees a seamless stream: ack → capture question → record confirmation → full advice. No "capturing" mode break from the user's perspective.

**Scenario 2 — Onboarding with volunteered info (fact parking).**

1. Fresh user registers, lands in onboarding, reaches `base_personal`.
2. Fyn asks: *"Could you tell me about yourself — date of birth, marital status?"*
3. User answers: *"I'm 40, married to Angela, 45."*
4. `OnboardingFactExtractor` runs: `personal.marital_status='married'`, `spouse.first_name='Angela'`, `spouse.age_hint=45`. All three merged into `ai_conversations.onboarding_parked_facts`. **Parking only — NO writes to `users.*` or `family_members` happen at this step.** Backing-record writes happen only when the relevant state (`base_personal`, `base_spouse`, …) reaches its own commit point via the existing grouped_extract tool handlers, or at `STATE_PROFILE_REVIEW_FAMILY` / `STATE_PROFILE_REVIEW_EXPENDITURE` confirmation. This keeps the fact extractor speculative and reversible — a regex misfire (e.g. "my wife's sister Angela" extracting `spouse.first_name='Angela'`) lives in the parking column until the state handler decides whether to apply it.
5. State machine advances. `base_personal` → `base_spouse`. Director consumes parked facts for `spouse` bucket, sees `first_name` and `age_hint` present but `email` missing. Emits a targeted follow-up: *"Thanks for letting me know about Angela (age 45). Could I get her email? That'll let me set up a linked account for her."*
6. User: *"angela@example.com"*.
7. `OnboardingFactExtractor` adds `spouse.email='angela@example.com'` to parking. Director now has all three required spouse fields. `SpouseLinkingService::createFromParkedFacts()` creates the linked spouse `User` row with name, approximated DOB (from `age_hint`), email. Director advances to `base_dependants`.

**Scenario 3 — Profile review pause with retraction.**

1. Continuing Scenario 2. User completes `base_dependants` answering "two kids, Sam 8 and Eli 6". Parked facts: `dependants.count_hint=2`, `dependants.people_hint=[{name:Sam, age_hint:8},{name:Eli, age_hint:6}]`. State advances to `STATE_PROFILE_REVIEW_FAMILY`.
2. Director emits `onboarding_layout_change` SSE event with `mode='standard'`. Frontend Vuex commits `onboardingLayout='standard'`. `AppLayout.vue`'s docked chat aside shrinks from 712px to **356px**. `AppLayout.vue` removes the dashboard blur class. `AppLayout.vue`'s `onboardingLayout` watcher stores the current route (typically `/dashboard`) in `preProfileRoute` and pushes `/profile` — so `UserProfile.vue` renders behind the chat with the user's captured personal, spouse, and dependant data visible. The 356px chat holds only the director's "Is this correct?" prompt and its confirmation bubble — no duplicate in-chat summary.
3. Director asks: *"Is this correct? Any other family or details to add here?"*
4. User: *"Actually my DOB is 12 March 1985, not 1986."*
5. Retraction handler in the director detects the contradiction, emits an `update_profile` tool call with `date_of_birth='1985-03-12'`, persists the message, replies *"Got it — updated your DOB from 1 Jan 1986 to 12 March 1985."*. On the next render cycle `UserProfile.vue` behind the chat reflects the new DOB (the page refetches when the user returns to it via the `wide` transition).
6. User: *"Yes, that looks right."*
7. Director advances to `base_employment`. Emits `onboarding_layout_change` with `mode='wide'`. `AppLayout.vue`'s watcher pushes the router back to `preProfileRoute` (`/dashboard`). Chat aside expands back to **712px**. Dashboard blurs.

**Scenario 4 — Resume-from-where-left-off (currently broken).**

1. User previously left off at `base_employment`. They return to `/onboarding/welcome` the next day.
2. `Onboarding` Vue component detects `users.onboarding_completed=false && onboarding_fyn_step='base_employment'` on mount. Calls `POST /api/ai-chat/conversations/{latestId}/action` with `{action:'resume'}`.
3. `AiChatController::action()` routes to the director (onboarding conversation). Director emits: *"Welcome back, {firstName}. Last time we were on your employment — I'd just asked about your role. Want to continue from where we left off, or start over?"* plus two action bubbles (`Continue`, `Start over`).
4. User clicks `Continue` → client calls `/action` with `{action:'continue'}`. Director resumes normal dispatch at `base_employment`.
5. (Alternative) User clicks `Start over` → client calls `/action` with `{action:'restart'}`. Director deletes prior `AiMessage` rows for the conversation, resets `onboarding_fyn_step='path_choice'`, replies *"No problem — let's start fresh."*. Fresh onboarding begins.

**Scenario 5 — Unhappy path: orchestrator malformed handoff.**

1. Post-onboarding user asks something that triggers advice Fyn to try to delegate.
2. Advice Fyn emits `delegate_to_capture` with an empty `entity_types` array (LLM hallucination).
3. Orchestrator validates — empty `entity_types` fails the check. Logs `[FynPersonaOrchestrator] malformed delegate_to_capture` warning with raw params. Does NOT transition to capturing state. Returns advice Fyn's text response (minus the stripped tool call) to the user.
4. User sees a normal advice response. The malformed handoff is invisible.
5. Ops reviews the log; if a pattern emerges, updates the advice prompt or the tool schema.

**Scenario 6 — Unhappy path: capture-mode timeout.**

1. User is in `capturing` state with `pending_advice_question` set. They type 6 unrelated messages without ever providing the captured fields Fyn is asking for.
2. `turns_in_capture` reaches `config('fyn.capture_max_turns', 6)`. Orchestrator force-flips to `advice` state, drops `pending_advice_question`, logs `capture timeout` warning, emits a user-visible fallback: *"Let me come back to what you were asking — it's easier if you add those details on the page rather than here."* plus a `navigate_to_page` tool call to the appropriate module.

---

## 5. Functional Requirements

MoSCoW-prioritised. Every requirement references touchpoints from the amended spec/plan.

### Must-have

- **FR-M1:** `AiChatController::chat()` must route post-onboarding turns to `FynPersonaOrchestrator::dispatch()` when `FYN_PERSONA_SPLIT=true`, and preserve the existing `CoordinatingAgent::chat()` path when the flag is off. _Touches: `app/Http/Controllers/Api/AiChatController.php:149-162` (3-way match branch)._
- **FR-M2:** `FynPersonaOrchestrator` must read/write `ai_conversations.persona_state` (new nullable JSON column) and transition between `advice` and `capturing` states per the handoff contract. Malformed delegates log a warning and fall through; capture-mode timeout (≥ `config('fyn.capture_max_turns', 6)` turns) force-flips to advice with a user-visible fallback message. _Touches: `app/Services/AI/FynPersonaOrchestrator.php` (new)._
- **FR-M3:** `FynPersonaInvoker` must build the persona-specific prompt (advice via renamed `AdvicePromptBuilder`, data-capture via new `DataCapturePromptBuilder`), filter the tool list to `FynPersonaRegistry::allowedTools(persona) ∪ handoffTools(persona)`, invoke the LLM via `CoordinatingAgent::chatWithPromptOverride()` using `toolsListOverride` (not `allowedTools`), and buffer text events for data-capture turns so the off-script filter can sanitise the full sentence list before yielding to SSE. _Touches: `app/Services/AI/FynPersonaInvoker.php` (new), `app/Agents/CoordinatingAgent.php:98-107` (existing signature)._
- **FR-M4:** `App\Services\AI\AdvicePromptBuilder` (renamed from `SystemPromptBuilder`) must produce the identical 10-layer prompt as today with no behaviour change. Every reference across `app/`, `tests/`, `config/` updated. _Touches: `app/Services/AI/SystemPromptBuilder.php` → `app/Services/AI/AdvicePromptBuilder.php`, `app/Traits/HasAiChat.php`, `app/Agents/CoordinatingAgent.php`, `app/Providers/AppServiceProvider.php` (if it has an explicit binding)._
- **FR-M5:** `DataCapturePromptBuilder` must emit a short (~500 token) capture-focused prompt carrying a `CaptureContext` value object (`reason`, `entity_types`, `fields_needed`, `pending_advice_question`). Post-onboarding only — NOT used by the director. _Touches: `app/Services/AI/Prompts/DataCapturePromptBuilder.php` (new), `app/ValueObjects/CaptureContext.php` (new)._
- **FR-M6:** `FynPersonaRegistry` must be a config-driven lookup (`config/fyn_personas.php`) with two entries (`advice`, `data_capture`). Integrity test asserts every listed tool exists in `AiToolDefinitions` / `XaiToolDefinitions` and every `handoff_tool` is in `HandoffContract::internalToolNames()`. _Touches: `app/Services/AI/FynPersonaRegistry.php` (new), `config/fyn_personas.php` (new)._
- **FR-M7:** New tools `delegate_to_capture` and `capture_complete` must be added to both `AiToolDefinitions` and `XaiToolDefinitions`, exposed only via the invoker per-persona (never via the default `getTools()` path). Both are tagged internal and stripped from SSE by the orchestrator. _Touches: `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php`, `app/Services/AI/HandoffContract.php` (new)._
- **FR-M8:** The existing `QueryClassifier` must be promoted to the orchestrator level. When `classification['primary'] === QuerySchemas::DATA_ENTRY` AND `str_word_count($message) <= 40` AND `! QuerySchemas::isAdviceShaped($message)`, the orchestrator preselects `data_capture` and bypasses the advice invocation. Otherwise it falls through to advice and passes the classification into `AdvicePromptBuilder::build()` as today. Kill switch `FYN_CLASSIFIER_FAST_PATH=false` disables the fast-path only. _Touches: `app/Services/AI/QueryClassifier.php` (existing, promoted), `app/Constants/QuerySchemas.php` (add `isAdviceShaped()`)._
- **FR-M9:** `create_will` / `update_will` AI tools must be added (`AiToolDefinitions`, `XaiToolDefinitions`, `CoordinatingAgent` handlers). Will schema gains `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts` columns via unconditional migration; `executor_name` exists already. Cache invalidation: handler calls `$this->invalidateUserCache($user->id)` after write. _Touches: `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`, new migration on `wills` table, `app/Models/Estate/Will.php` (fillable update)._
- **FR-M10:** `create_power_of_attorney` / `update_power_of_attorney` AI tools must be added against the existing `App\Models\Estate\LastingPowerOfAttorney` model. Tool schema uses existing column names (`lpa_type` with values `property_financial` / `health_welfare`); primary attorney is a `LpaAttorney` related row via the `attorneys()` relationship (not a flat string). No new model, no new migration, no new controller. Cache invalidation on write. _Touches: `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`. Existing: `app/Models/Estate/LastingPowerOfAttorney.php`, `app/Models/Estate/LpaAttorney.php`._
- **FR-M11:** `ai_messages` must gain a nullable `persona` column (`enum('advice','data_capture')`), populated by the orchestrator per turn. Foreign key remains `conversation_id` (confirmed via audit). `AiMessage::create([...])` calls in the orchestrator use `conversation_id`, NOT `ai_conversation_id`. _Touches: new migration, `app/Models/AiMessage.php` (fillable)._
- **FR-M12:** `ai_conversations` must gain two nullable JSON columns — `persona_state` (see FR-M2) and `onboarding_parked_facts` (see FR-M19). Defaults backfilled for existing rows in the migration. _Touches: two new migrations, `app/Models/AiConversation.php` (fillable + casts)._
- **FR-M13:** `AiConversationFactory` and `AiMessageFactory` must exist before any AI-related test runs. Both add `HasFactory` trait to their models if missing. _Touches: `database/factories/AiConversationFactory.php` (new), `database/factories/AiMessageFactory.php` (new), `app/Models/AiConversation.php`, `app/Models/AiMessage.php`._
- **FR-M14:** `OnboardingChatDirector` must be extended with `STATE_PROFILE_REVIEW_FAMILY` (after `base_dependants`) and `STATE_PROFILE_REVIEW_EXPENDITURE` (after `expenditure`). Each review state emits an `onboarding_layout_change` SSE event with `mode='standard'`, renders the "Is this correct?" prompt, and on confirmation emits the wide-mode event before advancing. The frontend handler for `mode='standard'` in `AppLayout.vue` ALSO pushes the Vue Router to `/profile` so `UserProfile.vue` renders behind the shrunken chat; on `mode='wide'` it returns to the stored pre-pause route. Director NOT deleted. _Touches: `app/Services/Onboarding/OnboardingStateMachine.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, `resources/js/layouts/AppLayout.vue` (watcher + route push)._
- **FR-M15:** `STATE_BASE_EMPLOYMENT` bubbles must be updated: rename `Employed` to `Full-time`, remove `Other`. A new `STATE_BASE_EMPLOYMENT_MORE` state must exist; after the first job is captured, director asks "Any other jobs to add?". Yes → loops back to employment capture. No → advances to `STATE_BASE_EXPENDITURE` (note: constant is `STATE_BASE_EXPENDITURE`, not `STATE_EXPENDITURE`). _Touches: `app/Services/Onboarding/OnboardingStateMachine.php`, `config/onboarding.php` if bubble config lives there._
- **FR-M16:** Spouse skip link — `STATE_BASE_SPOUSE` must emit `skip_link` metadata (`{label, color:'raspberry'}`) alongside the bubble question. Frontend renders as an inline raspberry-500 underlined `<button>`. Click posts `{action:'skip'}` to `POST /api/ai-chat/conversations/{id}/action`, which advances the user to `STATE_BASE_DEPENDANTS` without writing spouse data. _Touches: `app/Services/Onboarding/OnboardingChatDirector.php`, new action endpoint, `resources/js/components/Fyn/FynOnboardingChat.vue`._
- **FR-M17:** Conversational retraction — the asset-capture prompt layer (`OnboardingPromptBuilder::assetCaptureInstructions`) must gain a retraction block. When the LLM detects a contradiction ("actually I'm married"), it emits `update_profile` or `update_record` and director confirms with a brief before→after acknowledgment. Risk recalculation observer fires automatically via the existing Eloquent update hook chain. _Touches: `app/Services/Onboarding/OnboardingPromptBuilder.php`._
- **FR-M18:** `POST /api/ai-chat/conversations/{id}/action` endpoint with body `{action:'resume'|'continue'|'restart'|'skip'}`. Validates enum, routes to director (if `$inOnboarding`) or orchestrator (if `$splitEnabled`). Actions NOT persisted as `AiMessage`. Route added to `PreviewWriteInterceptor::EXCLUDED_ROUTES`. _Touches: `routes/api.php`, `app/Http/Controllers/Api/AiChatController.php` (new `action` method), `app/Http/Middleware/PreviewWriteInterceptor.php`._
- **FR-M19:** `OnboardingFactExtractor` service must run on every user turn during onboarding, regex-extract structured facts into `personal` / `spouse` / `dependants` / `employment` / `expenditure` buckets, and merge into `ai_conversations.onboarding_parked_facts`. Each state handler consults parking before emitting its question: all required fields present → silently apply to backing record and advance; some fields present → targeted follow-up asking only for gaps; nothing present → canonical question. Regex runs on original-case input (NOT `ucwords(mb_strtolower(...))`). _Touches: `app/Services/Onboarding/OnboardingFactExtractor.php` (new), `app/Services/Onboarding/OnboardingChatDirector.php`._
- **FR-M20:** Resume-from-where-left-off — director detects a qualifying returning user (`onboarding_completed=false` AND `onboarding_fyn_step != null` AND prior `AiMessage` rows exist) when the frontend sends `{action:'resume'}`. Emits a welcome-back greeting referencing the saved step and last assistant message, plus two action bubbles (`Continue`, `Start over`). `continue` resumes at the saved step; `restart` hard-deletes prior `AiMessage` rows, resets `onboarding_fyn_step='path_choice'`. _Touches: `app/Services/Onboarding/OnboardingChatDirector.php`._
- **FR-M21:** Wide-chat onboarding layout — two widths only. The docked chat aside in `AppLayout.vue` is `w-[712px] max-w-[calc(100vw-15rem)]` when onboarding is active AND `onboardingLayout !== 'standard'`, and `w-[356px]` otherwise (profile-review pauses AND non-onboarding chat). `FynOnboardingChat.vue` wraps the onboarding chat surface; when `docked: true` it lets the aside own the width (`w-full h-full`). `AppLayout.vue` applies `filter: blur(4px)` to the dashboard content container while the route is an onboarding route AND `onboardingLayout === 'wide'`. On `onboardingLayout === 'standard'` the AppLayout watcher pushes Vue Router to `/profile`; on `'wide'` it returns to the stored pre-pause route. No icons on any capturing pill (CLAUDE.md §14). **Do not re-introduce `w-[525px]` or `max-w-4xl` — those are superseded anti-values.** _Touches: `resources/js/components/Fyn/FynOnboardingChat.vue`, `resources/js/layouts/AppLayout.vue`._
- **FR-M22:** **DROPPED (2026-04-22 session 3).** `ProfileReviewPanel.vue` is no longer rendered during pauses. The review surface is the existing `UserProfile.vue` page on `/profile` (reached via the AppLayout route push in FR-M21). Rendering a separate summary inside the chat aside was redundant — `UserProfile.vue` already carries every captured field. The file stays in the repo unused; `FynOnboardingChat.vue` no longer imports it. Edits during the pause still happen via chat retraction only. _Touches: none — removal of the import and render slot in `FynOnboardingChat.vue`._
- **FR-M23:** Vuex `resources/js/store/modules/aiChat.js` must gain two new state properties — `personaMode` (`advice`|`capturing`) driven by `persona_state_change` SSE events from the orchestrator, and `onboardingLayout` (`standard`|`wide`) driven by `onboarding_layout_change` SSE events from the director. Existing `streamingText` and `done` handlers unchanged. _Touches: existing file._
- **FR-M24:** Post-onboarding chat `AiChatPanel.vue` must gain a capturing-state pill (`Updating your records`, horizon-500 text on savannah-100 background, no icon/spinner), placeholder swap (`Ask Fyn anything…` → `Tell Fyn the details…`), record-card row on `capture_complete`, and preview-mode CTA rendering. _Touches: `resources/js/components/Shared/AiChatPanel.vue`._
- **FR-M25:** Preview-mode — advice Fyn's prompt layer gains a preview-mode instruction block that tells it NOT to emit `delegate_to_capture` and instead reply with *"I can't save data in preview mode — but if you sign up, I'll capture this straight away."* plus a Sign up CTA. Data writes still intercepted by `PreviewWriteInterceptor` as an additional safety net. _Touches: `app/Services/AI/Prompts/CoreIdentity.php` or equivalent preview-aware layer._
- **FR-M26:** Feature flags — `FYN_PERSONA_SPLIT` (default false) gates the orchestrator. `FYN_CLASSIFIER_FAST_PATH` (default true) gates the classifier fast-path within the orchestrator (only meaningful when split is on). No third flag. _Touches: `config/fyn.php` (new)._

### Should-have

- **FR-S1:** Observability — `FynPersonaInvoker::invoke()` logs `persona`, `prompt_length`, `tool_count`, `user_id`, `conversation_id` at `info` level per turn. Orchestrator logs every state transition at `info` and every malformed handoff / timeout at `warning`. _Touches: `app/Services/AI/FynPersonaInvoker.php`, `app/Services/AI/FynPersonaOrchestrator.php`._
- **FR-S2:** Weekly drift audit — a scheduled Pest test (or artisan command) samples fast-path decisions and re-runs the messages through advice Fyn, flagging any where the classifier misrouted a genuine advice query. Output goes to logs, not a new dashboard. _Touches: `app/Console/Commands/AuditFastPathDrift.php` (new), `app/Console/Kernel.php`._
- **FR-S3:** Rollback mechanics — if `FYN_PERSONA_SPLIT` is flipped off mid-incident, any conversations already in `capturing` state must handle gracefully. The `CoordinatingAgent::chat()` fallback path ignores `persona_state` and operates as today. Next orchestrator dispatch (after flag re-enable) re-reads state and continues. No data loss. _Touches: verify via integration test in the Pest suite._

### Nice-to-have

- **FR-N1:** Future-persona stub — document in `config/fyn_personas.php` comments how a third persona (e.g. `advisor`) would register. No code required, docs only. _Touches: `config/fyn_personas.php`._
- **FR-N2:** Mobile wide-chat parity — the iOS `MobileFynChat.vue` view could inherit the wide / blur pattern. Not in this release. _Touches: future task._
- **FR-N3:** ProfileReviewPanel polish — add gentle fade transitions when fields appear/update, stagger animation on first render. Default to instant render in v1. _Touches: `resources/js/components/Onboarding/ProfileReviewPanel.vue`._

---

## 6. User Flow & UX/Design

### Flow — onboarding happy path (amended)

```
/register?from=fyn → /onboarding/welcome
  │
  ├── path_choice (wide chat, dashboard blurred)
  ├── base_personal (fact extractor populates parking as user types)
  ├── base_spouse (skip-link available; parking may short-circuit to gap-fill only)
  ├── base_dependants (parking-driven — ask only for names/DOBs when count_hint known)
  │
  ├── STATE_PROFILE_REVIEW_FAMILY  ← NEW PAUSE
  │   ├── chat aside shrinks from 712 to 356
  │   ├── dashboard un-blurs
  │   ├── router pushes /profile → UserProfile.vue renders in main canvas
  │   ├── chat shows ONLY "Is this correct?" prompt + confirmation bubble
  │   │   (no in-chat summary panel — UserProfile.vue is the review surface)
  │   └── "Is this correct?" → Yes → router returns to pre-pause route,
  │       chat returns to 712, dashboard re-blurs, advance to employment
  │
  ├── base_employment (Full-time / Part-time / Self-employed / Retired / Not working — no Other)
  ├── base_employment_more ← NEW LOOP  ("Any other jobs?" Yes loops back; No advances)
  ├── expenditure
  │
  ├── STATE_PROFILE_REVIEW_EXPENDITURE  ← NEW PAUSE
  │   └── same 356-chat + /profile route push pattern (no in-chat panel);
  │       confirmation returns to pre-pause route and advances
  │
  ├── asset_capture (journey-focused: retirement / protection / etc.)
  │   ├── director delegates to CoordinatingAgent::chatWithPromptOverride
  │   │   with OnboardingPromptBuilder (unchanged)
  │   ├── FR-M14 off-script filter applies as today
  │   └── retraction handler active
  │
  ├── add_more
  └── done → /dashboard
```

### Flow — post-onboarding (new, via orchestrator)

```
POST /api/ai-chat/conversations/{id}/messages
  │
  ├── AiChatController → FynPersonaOrchestrator::dispatch()
  │   │
  │   ├── reads persona_state (advice by default)
  │   │
  │   ├── runs QueryClassifier::classify(message)
  │   │   ├── primary=DATA_ENTRY + word_count<=40 + no advice phrases → fast-path to data_capture
  │   │   └── otherwise → advice
  │   │
  │   ├── invokes persona via FynPersonaInvoker
  │   │   ├── advice:        AdvicePromptBuilder + advice-side tools + delegate_to_capture
  │   │   └── data_capture:  DataCapturePromptBuilder + create_* tools + capture_complete
  │   │
  │   ├── streams response tokens to SSE (strips internal tool calls)
  │   │
  │   ├── if advice emitted delegate_to_capture:
  │   │   ├── set persona_state.current='capturing', pending_advice_question=user_message
  │   │   ├── invoke data_capture persona with CaptureContext
  │   │   └── on capture_complete: reset state, re-invoke advice with primed suffix
  │   │
  │   └── if data_capture emitted capture_complete (no pending_advice_question):
  │       └── reset persona_state.current='advice', continue
  │
  └── SSE → frontend (personaMode, record cards, capturing pill)
```

### Flow — resume-from-where-left-off (fix)

```
User lands on /onboarding/welcome
  │
  └── Onboarding.vue mounted()
      ├── users.onboarding_completed === false ?
      │   └── users.onboarding_fyn_step !== null ?
      │       └── POST /api/ai-chat/conversations/{latestId}/action {action:'resume'}
      │           └── director emits welcome-back greeting + [Continue, Start over] bubbles
      │               ├── Continue  → POST /action {action:'continue'} → resume at saved step
      │               └── Start over → POST /action {action:'restart'} → wipe messages + reset step
      │
      └── otherwise: fresh start (normal path_choice flow)
```

### UX/Design notes

- **Design system:** `fynlaDesignGuide.md v1.3.0` at `/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md`. All colour tokens: raspberry-500 (skip link, Sign up CTA), horizon-500 (pill text, body text), savannah-100 (pill background, hover), spring-500 (success states if any), violet-500 (focus rings), eggshell-500 (page background). No amber / orange / primary-* / secondary-* / gray-* tokens.
- **Icon surface:** both the post-onboarding chat (`AiChatPanel.vue`) and the onboarding chat (`FynOnboardingChat.vue`) are the **Fyn chat window** — a banned surface per CLAUDE.md §14. No icons, no emoji, no unicode glyphs anywhere in the chat UI, capturing pill, record cards, preview CTA, skip link, or profile review panel. The raspberry skip link is inline text only (no arrow, no chevron). The capturing pill has no spinner. Record cards are text + "View" link. Preview CTA is a plain primary button.
- **Reusable components:** existing `AiChatPanel.vue`, `BaseButton`, `BaseCard`, `FormModal`. New components created: `FynOnboardingChat.vue`, `ProfileReviewPanel.vue`.
- **Responsive behaviour:** wide onboarding chat collapses to full-width on mobile viewports (`sm` breakpoint) where `max-w-4xl` has no effect. Pause-state `w-[525px]` also adjusts responsively. No dedicated mobile layout work in this release (see §7 Out of Scope).
- **iOS Capacitor:** mobile inherits the SSE event schema and Vuex state changes via the shared `resources/js/store/modules/aiChat.js`. The wide-chat layout and dashboard blur are NOT implemented in the mobile `MobileFynChat.vue` component in this release — mobile onboarding retains its current visual treatment. No image imports in built JS (avoids WKWebView MIME-type blank-screen).
- **Accessibility:** skip link is a focusable `<button>` with visible focus ring (violet-500). Profile review panel `<dl>` uses semantic dt/dd pairing. Action bubbles (`Continue`, `Start over`) are `<button>` elements with full keyboard navigation.
- **British spelling** in all user-facing text ("customise", "optimise", "centre", "organisation"). Acronyms spelled out: "Defined Contribution", "Defined Benefit", "Lasting Power of Attorney", "Money Purchase Annual Allowance" — ISA and LPA are the only exceptions. No financial-score numbers ever shown to users.

### Reference artefacts

- Amended spec: `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md` (commit `455cba3`).
- Amended plan: `April/April21Updates/plan-fyn-persona-split.md` (see AMENDMENTS section at top).
- Current onboarding director in production: `app/Services/Onboarding/OnboardingChatDirector.php` (FR-M9..FR-M15 shipped).
- Design guide: `/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md` v1.3.0.
- CLAUDE.md rules: Icons §14, CSS governance §12, Colours §9–§11, No scores §13.

---

## 7. Out of Scope

- **Additional personas beyond `advice` and `data_capture`.** The registry accommodates them; none are built here.
- **Advisor mode / advisor product.** Separate future release.
- **Mobile (iOS) wide-chat + dashboard blur visual treatment.** Mobile inherits backend/SSE behaviour only; the wide-chat visual work is desktop-only in this release.
- **Backfill of historical `ai_messages.persona`.** The new column is null for pre-existing rows. No retroactive tagging.
- **LLM-based classifier** (for intent or retraction). Both use rule-based/regex approaches with deterministic failure modes.
- **Inline field editing in the ProfileReviewPanel.** Edits happen via chat only.
- **Runtime mutability of the persona registry** (hot-reload from DB). File-based `config/fyn_personas.php` only. Add runtime mutability when a second external persona (advisor product) lands.
- **Deletion of `OnboardingChatDirector` or `OnboardingPromptBuilder`.** Both stay. The director is extended in place.
- **`OnboardingMemoryExtractor` as a separate class.** The parking column is the memory. Removed from plan.
- **Classifier as a new class (`FynIntentClassifier`).** Promoted the existing `QueryClassifier` instead.
- **New `PowerOfAttorney` model.** Existing `LastingPowerOfAttorney` is the target.
- **New route `/api/power-of-attorneys`.** LPA CRUD via the existing (assumed) controller for `LastingPowerOfAttorney`. AI-tool handlers do NOT add a new REST endpoint.
- **Chat UI redesign beyond the capturing pill, record cards, skip link, profile panel, and wide/standard modes.** Typography, colour, bubble shapes — unchanged.
- **Integration with Revolut, Awin, or any external service.** Out of this release's scope.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Orchestrator bug corrupts `persona_state`, traps user in `capturing` state | Med | Med | Capture-mode timeout (FR-M2) auto-recovers after 6 turns. Feature flag allows instant rollback. State is JSON on the conversation — direct DB fix available. |
| Director extensions regress FR-M9..FR-M15 behaviour shipped on `onboardingFyn` | Med | High | `StateMachineWalkthroughTest`, `AssetCaptureOffScriptFilterTest`, `AssetCaptureMultiEntityTest` must pass on every commit. `onboarding.fyn_flow_enabled` flag can disable Fyn-driven onboarding entirely. |
| `QueryClassifier` promotion misroutes advice-shaped messages to data-capture | Low | Med | `isAdviceShaped()` heuristic + word-count cap + `FYN_CLASSIFIER_FAST_PATH=false` kill switch. Weekly drift audit (FR-S2) flags systematic misrouting. |
| `conversation_id` vs `ai_conversation_id` confusion introduces silent test failures | Med | Low | AMENDMENTS §F explicitly documents the correct column name. Code review + test suite will catch. |
| `CoordinatingAgent::chatWithPromptOverride` signature mismatch ships tool-filtering no-op | Low | High | AMENDMENTS §F documents the 8-param signature and `toolsListOverride` slot. Unit test on invoker asserts tool list is correctly passed. |
| New `ai_messages.persona` enum migration collides with existing enum type-truncation bug | Low | Med | Column uses its own migration (additive, no `ALTER ENUM`). Plan explicitly calls this out per `AutoRiskCalculatorTest` enum truncation carry. |
| Will migration adds columns that conflict with future Will Builder UI work | Low | Low | Columns are nullable. Will Builder can opt-in. |
| LPA tool schema drifts from actual `LastingPowerOfAttorney` column names | Med | Med | AMENDMENTS §C documents correct `lpa_type` / enum values. Feature test asserts tool creates a real `LastingPowerOfAttorney` + `LpaAttorney` row with matching fields. |
| Action endpoint receives sentinel strings instead of actions due to old frontend code | Low | Low | Complete removal of sentinel-string code paths in `aiChatService.js` and `FynOnboardingChat.vue`. Old sentinels are not parsed by the action endpoint — they'd return 422 validation error, making the bug visible. |
| Wide-chat CSS at `28rem` accidentally narrower than existing `525px` panel | N/A | Low | AMENDMENTS §F mandates Tailwind `w-[525px]` for standard and `max-w-4xl` (56rem) for wide. No hardcoded `rem` values. |
| `ucwords(lowercased)` regex bug in spouse-name extraction silently extracts wrong name | Med | Med | AMENDMENTS §F mandates running the regex on original-case input. Feature test covers Angela case. |
| `describeStep()` undefined `$user` variable crashes resume greeting | High (would crash every resume) | High | AMENDMENTS §F mandates passing `?User $user` as a parameter. Feature test on resume flow exercises every step label. |
| Preview-mode users bypass advice's "can't save" prompt and hit `PreviewWriteInterceptor` 403 | Low | Low | Prompt-layer instruction (FR-M25) is the first-line defence. Interceptor is the second. Both in play = safe. |
| SSE event schema additions (`persona_state_change`, `onboarding_layout_change`) not consumed by mobile app | Low | Low | Mobile chat view explicitly out-of-scope for visual changes. Backend emits events regardless; mobile ignores them gracefully. |
| Capture turn's buffered text delays streaming → user perceives lag | Med | Low | Capture prompts are short — the full response arrives in 1–2 seconds typically. Off-script filter needs full-sentence context to work. Accept the tradeoff. |

### Technical dependencies

- **Existing services kept unchanged:** `CoordinatingAgent`, `HasAiChat` trait, `OnboardingChatDirector`, `OnboardingPromptBuilder`, `OnboardingStateMachine`, `TaxConfigService`, `QueryClassifier`, `QuerySchemas`, `PrerequisiteGateService`, `AdviceReviewService`, `PreviewWriteInterceptor`.
- **Existing models used:** `App\Models\AiConversation` (extended), `App\Models\AiMessage` (extended), `App\Models\User`, `App\Models\Estate\Will` (extended with 3 columns), `App\Models\Estate\LastingPowerOfAttorney` (unchanged), `App\Models\Estate\LpaAttorney` (unchanged). Every seeded model via the existing `TestUsersSeeder`, `ChrisUserSeeder`, `PreviewUserSeeder`.
- **External APIs:** Anthropic Claude 4.x and/or xAI Grok — the existing `HasAiChat::chat()` provider selection path is unchanged. New tool schemas must validate in both provider formats.
- **Observer dependencies:** existing `Auditable` trait on `LastingPowerOfAttorney` and `Will` handles audit logging. Risk recalculation observers fire automatically on `User::update()` from retraction handlers. `invalidateUserCache($userId)` called by write handlers invalidates CoordinatingAgent's analysis cache.
- **Laravel framework:** Laravel 10, PHP 8.2, MySQL 8 (for JSON column + enum support). No version bumps in this release.
- **Test framework:** Pest 2 with `RefreshDatabase` trait. `TaxConfigurationSeeder` auto-seeded in `beforeEach()`.

### Sequencing dependencies

- **Blocks on:** `onboardingFyn` branch being deployed to dev (Gate 1) and verified green. Once dev is stable with FR-M9..FR-M15 live, `feature/fyn-persona-split` branches off `onboardingFyn` and this release proceeds.
- **Is blocked by:** nothing external. All dependencies are in-repo.
- **Blocks:** Advisor product persona registration (future). Mobile Fyn onboarding parity (future). Any future persona.

### Residual concerns from codebase audit

Two audit items deliberately deferred:

1. **Exact `FynOnboardingChat.vue` mount point.** The current onboarding flow mounts via a component imported at `resources/js/router/index.js:371` (referred to as `Onboarding`). The exact file (likely `resources/js/views/Onboarding.vue` or similar) must be identified at implementation time. No blocking issue — just a file-location confirmation step at implementation.
2. **`OnboardingStateMachine::getState()` public method.** Task 24's test (plan text) calls `OnboardingStateMachine::getState('base_employment')` but this is not currently a public method. Either add a public static `getState(string $name): ?array` that exposes the state definition, or rewrite the test to read via whatever public method currently exposes bubble config. Implementer's choice; both are acceptable.

All other audit findings (19 total) are resolved in the amended spec/plan AMENDMENTS section.

---

## 9. Document History

| Date | Change | By |
|---|---|---|
| 2026-04-21 | Initial draft following `superpowers:brainstorming` | prd-writer skill |
| 2026-04-21 | Spec revision 1 — expanded tool inventory, onboarding migration, classifier fast-path, chat UI pulled into scope | user + Claude |
| 2026-04-21 | Onboarding UX overhaul added (wide chat, pauses, skip, multi-job, retraction, memory, resume, parking) | user + Claude |
| 2026-04-21 | Codebase audit by `feature-dev:code-explorer` and `feature-dev:code-architect` — 19 conflicts and architectural concerns surfaced | audit agents |
| 2026-04-21 | Spec amended — director retention, existing `LastingPowerOfAttorney`, `QueryClassifier` reuse, action endpoint, parking-as-memory, 2-flag set, `FynOnboardingChat.vue` separation | user + Claude |
| 2026-04-21 | Plan amended (AMENDMENTS section at top) — task drops/rewrites, `conversation_id` fix, `chatWithPromptOverride` signature fix, `STATE_BASE_EXPENDITURE` typo fix, regex fix, Tailwind utilities for chat widths, factory prerequisite task | Claude |
| 2026-04-21 | PRD written | prd-writer skill |
| 2026-04-22 | Session 3 amendment — profile-review pause now pushes Vue Router to `/profile` (existing `UserProfile.vue`) so `UserProfile.vue` renders behind the shrunken chat; chat widths normalised to two states (712 wide / 356 standard) and the earlier `w-[525px]` / `max-w-4xl` literals retired as anti-values. FR-M14, FR-M21, FR-M22, Scenario 3, and §6 Flow amended. Corresponding AMENDMENTS §L added to the plan; spec amended. | CSJ + Claude |
| 2026-04-22 | Session 3 follow-up — `ProfileReviewPanel.vue` DROPPED. It was redundant with `UserProfile.vue` on the `/profile` route (which already shows every captured field). FR-M22 marked DROPPED. `FynOnboardingChat.vue` no longer imports or renders the panel. Spec/plan updated correspondingly. | CSJ + Claude |
