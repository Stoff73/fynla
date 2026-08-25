# Audit Evidence Bundle — 24 April 2026 (v2 — post three-pass review)

---

## §0 — Canonical two-Fyn contract (source of truth)

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:
- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. The rest of this bundle is evidence grounded in the code against which this contract must hold.

---


**Purpose:** Ground-truth record of what the code on `main` and `feature/fyn-persona-split` actually says, for use by reviewers auditing the four planning docs (`fyn-system-map.md`, `fyn-integrated-plan.md`, `enterprise-verdict.md`, archived `INDEX.md`).

**Method:** Direct file reads, git diff between branches, Explore agent sweep. No runtime verification.

**How to use:** Every claim in this file carries a file:line anchor. If a reviewer wants to dispute an entry, re-read the referenced code.

**v2 corrections (24 April PM, post three-pass review — see `docs-three-pass-review.md` for derivation):**
- §3.2 retracted: `FynPersonaOrchestrator`'s capture path DOES have entity extractor wiring via `FynPersonaInvoker::emitGapFillFromCaptureContext` (see B-1 commit `37b6a4b`). The real gap is extractor coverage breadth, not wiring absence.
- §4 tool counts corrected: 37 Anthropic / 33 xAI (not 29 / 23). Direction of the parity gap is inverted — Anthropic has MORE tools than xAI; `create_holding` + onboarding `capture_*` tools are Anthropic-only.
- Stale line anchors refreshed: cache metrics 467-469→569-572, `update_record` blocklist 2489-2490→3134, audit log 703/705→768/770, `summariseTool*` 637/670→719/749, tool-use loop 436→539.
- §1: 178 commits behind → **179** (one off).
- §14: framing of "three undisclosed processors" refined — Anthropic is partially disclosed (document extraction only; chat use is not scoped).

---

## 1. Branch state (24 April 2026, ~14:00 BST)

| Branch | Tip | Commits ahead of `origin/main` | Commits behind `origin/main` |
|---|---|---|---|
| `origin/main` | `63344da` (session 68 handover) | 0 | 0 |
| `origin/feature/fyn-persona-split` | (tip) | **68** | **179** |
| `origin/onboardingFyn` (PR #214) | (open) | unknown | drift large — CSJTODO marks as "superseded by persona-split" |

**Doc claim (CSJTODO session 69 §Branch state):** "`feature/fyn-persona-split` is 68 commits ahead of main, 72 behind".

**Ground truth:** 68 ahead, **179 behind** — not 72. `main` moved during session 68 (PR #228 merged 99 commits dev→main). Every rebase-effort estimate in the docs that assumes 72-commit drift is understated by ~2.6×. (v2 correction: earlier copies of this bundle stated 178 — the accurate count via `git rev-list --count origin/feature/fyn-persona-split..origin/main` is 179.)

---

## 2. Files referenced by the docs — does the file exist on the branch the doc implies?

| File | `main`? | `feature/fyn-persona-split`? | Doc implies it exists on… |
|---|---|---|---|
| `app/Services/Onboarding/OnboardingChatDirector.php` | **NO** | YES (1985 LOC) | both (§4.1, §12) |
| `app/Services/Onboarding/OnboardingPromptBuilder.php` | **NO** | YES | both |
| `app/Services/Onboarding/OnboardingStateMachine.php` | **NO** | YES | both |
| `app/Services/Onboarding/OnboardingValueInterpreter.php` | **NO** | YES | both |
| `app/Services/Onboarding/OnboardingFactExtractor.php` | **NO** | YES | both |
| `app/Services/Onboarding/AssetCaptureEntityExtractor.php` | **NO** | YES (`app/Services/Onboarding/AssetCaptureEntityExtractor.php:1-665`, 665 LOC) | persona-split only |
| `app/Services/Onboarding/SpouseLinkingService.php` | **NO** | YES | both |
| `app/Services/Onboarding/HouseholdProvisioner.php` | **NO** | YES | persona-split (new) |
| `app/Services/AI/FynPersonaOrchestrator.php` | **NO** | YES (`app/Services/AI/FynPersonaOrchestrator.php:1-415`, 415 LOC) | persona-split |
| `app/Services/AI/FynPersonaInvoker.php` | **NO** | YES | persona-split |
| `app/Services/AI/FynPersonaRegistry.php` | **NO** | YES | persona-split |
| `app/Services/AI/HandoffContract.php` | **NO** | YES | persona-split |
| `app/Services/AI/Prompts/DataCapturePromptBuilder.php` | **NO** | YES | persona-split (Sprint 0.19 says delete it) |
| `app/Services/AI/Prompts/EmptyDataGuard.php` | **NO** | YES | persona-split |
| `app/ValueObjects/CaptureContext.php` | **NO** | YES | persona-split (Sprint 0.19 says may delete) |
| `config/fyn_personas.php` | **NO** | YES | persona-split |
| `config/fyn.php` | **NO** | YES | persona-split |
| `config/onboarding.php` | **NO** | YES | persona-split |
| `app/Services/Documents/AIExtractionService.php` | YES | YES | both |
| `app/Services/GDPR/ConsentService.php` | YES | YES | both |
| `app/Http/Controllers/Api/AgentInternalController.php` | YES | YES | both |
| `scripts/fynla_agent/` (Python sidecar) | YES | YES | both |
| `scripts/run_agent.py` | YES | YES | both |

**Implication:** The system-map conflates "exists on main" with "exists on persona-split" in §1, §4, §7, §26. A reader wanting to understand *production* state sees files that do not exist there.

---

## 3. Multi-entity capture — the W1 / T14 hotspot

**User's claim:** "Multi-entity capture does not work, never has, but needs to." — 24 April 2026 message.

### 3.1 On `main` (production)

- `app/Traits/HasAiChat.php:44` — `MAX_TOOL_CALLS_PER_TURN = 5`. Loop at line 539 on the branch (was 436 on `main`) continues if `stopReason === 'tool_use'` and count < 5. Multiple tool calls per turn ARE possible in principle.
- **Zero** deterministic multi-entity extraction code on main. No `AssetCaptureEntityExtractor`, no regex fallback, no post-stream gap-fill.
- Prompt layers (Layer 2 `ComplianceRules.php`, Layer 3 `FcaProcessInstructions.php`) do not contain multi-entity instructions.
- Result in practice: if the model decides to emit only one `create_*` tool call for "Aviva £300k and Vitality £100k", the second never happens, and the loop exits cleanly. **CONFIRMED BROKEN on main.**

### 3.2 On `feature/fyn-persona-split` — wired on both paths; coverage-breadth is the real gap

`AssetCaptureEntityExtractor` (`app/Services/Onboarding/AssetCaptureEntityExtractor.php:1-665`, 665 LOC):
- Docstring (lines 8-16, quoted verbatim):
  > *"The xAI/Anthropic models still drop a tool call on multi-entity messages ('I have Aviva life insurance £300k and Vitality critical illness £100k' emits only one create_protection_policy despite prompt instructions to emit both). This extractor runs *after* the LLM stream finishes, parses the original user message into a list of entity-shaped inputs, and the director emits synthetic tool_use / fill_form events for any entities the LLM missed."*
- Four focuses only: `protection`, `savings`/`budgeting`, `retirement`, `investment` (`app/Services/Onboarding/AssetCaptureEntityExtractor.php::extractForFocus`, match cases at `:48-58`).
- KNOWN_PROVIDERS regex list covers ~40 providers (`app/Services/Onboarding/AssetCaptureEntityExtractor.php:33-66`). Unknown providers / ambiguous phrasing → extractor returns `[]`, under-fill accepted.

**Where it is wired — BOTH paths (v2 correction — earlier versions of this bundle stated wiring was onboarding-only; that was wrong):**

1. `OnboardingChatDirector` (onboarding asset-capture turns) — wires the extractor inline (`app/Services/Onboarding/OnboardingChatDirector.php:1708-1720`).
2. `FynPersonaInvoker` (post-onboarding data-capture turns). The invoker injects `AssetCaptureEntityExtractor` at its constructor (line 48), records every `fill_form` the LLM emits during data-capture turns, then fires `emitGapFillFromCaptureContext()` at the `done` marker (line 175) and as a safety-net flush (line 200). The method itself lives at lines 251-300 and calls `$this->entityExtractor->extractForFocus()` + `findMissing()`. This wiring was added in commit `37b6a4b` ("feat(fyn): deterministic multi-entity gap-fill (B-1) + household provisioner (B-2)").

**The real gap is coverage breadth, not wiring absence:**

- Both paths share the same extractor, which only knows 4 focuses: `protection`, `savings`, `retirement`, `investment` (`app/Services/Onboarding/AssetCaptureEntityExtractor.php:48-58`).
- `FynPersonaInvoker::inferFocusesFromEntityTypes` (`app/Services/AI/FynPersonaInvoker.php:268-280`) maps `CaptureContext::entityTypes` into those 4 focuses and **silently drops** entity types the extractor can't handle: `goal`, `life_event`, `property`, `trust`, `will`, `power_of_attorney`, `business_interest`, `chattel`, `family_member`, `liability`, `estate_gift`, `investment_holding`. For those ~12 entity types, neither the orchestrator's capture turns nor the onboarding director's asset-capture turns get gap-fill.
- Within the 4 covered focuses, unknown providers (outside the KNOWN_PROVIDERS ~40-entry list) also silently return `[]`.

**Implication:** Multi-entity gap-fill exists on both paths for 4 of the 18+ create-type tools. The integrated-plan §5.1 / §7 T14 framing "persona-split fixes multi-entity" is a narrower truth than it sounds — it's fixed for those 4 focuses with known providers, broken everywhere else. Sprint 0.19's two-Fyn collapse is an architectural simplification, not a multi-entity fix; the real multi-entity work is extractor coverage expansion (and/or batch-shaped tool schemas per synthesis §7.2).

### 3.3 Entity types with NO extractor coverage even on persona-split

Goals, family members, life events, properties+mortgages, trusts, wills, powers of attorney, business interests, chattels, liabilities, estate gifts, holdings. That is ~12 of the 13 data-creation tools (`app/Services/AI/AiToolDefinitions.php::getTools`). Only 4 entity types (the four focuses) ever benefit from the regex fallback (`app/Services/Onboarding/AssetCaptureEntityExtractor.php:48-58`).

### 3.4 What the docs say vs reality

| Doc section | Claim | Reality |
|---|---|---|
| integrated-plan §5.1 | "Persona-split fixes multi-entity via the entity extractor" | Partially true on BOTH paths. The extractor runs on both onboarding asset-capture turns AND post-onboarding inline-capture turns (via `FynPersonaInvoker`). What it does NOT fix is (a) the 12+ entity types outside the 4-focus extractor coverage, (b) the long-tail of unknown providers outside KNOWN_PROVIDERS. |
| integrated-plan §7 T14 | "W1 duplication hotspot" | Duplication of the gap-fill *emission* logic between director and invoker is real — both files have parallel `emitGapFillFromCaptureContext` / `emitGapFillToolCalls` methods. The extractor itself is a single shared service. |
| CSJ correction §12 | Two Fyns, one capture stack (route capturing → `OnboardingChatDirector::handleInlineCaptureTurn`) | Method `handleInlineCaptureTurn` DOES NOT EXIST on persona-split (verified: `git grep handleInlineCaptureTurn feature/fyn-persona-split` → zero matches) — Sprint 0.19 proposes adding it. The current architecture has a separate `FynPersonaInvoker` stack for post-onboarding capture, which already has its own extractor wiring — so the collapse is de-duplication of the gap-fill emission code, not a multi-entity fix. |
| enterprise-verdict | (Does not directly address multi-entity) | The verdict's C11 mentions `AIExtractionService` but not this chat-side extractor. Gap in the verdict. |

**Net:** The multi-entity problem is narrower than originally stated in v1 of this bundle. Gap-fill runs on both paths today. The real multi-entity gap is: 4 of 18+ entity types covered, known-providers-only, and the two gap-fill emitters are duplicated (invoker vs director). Sprint 0.19's two-Fyn collapse solves the emitter duplication; it does NOT expand entity coverage — that's a Sprint 1+ body of work (batch-shaped tool schemas, extended extractor focuses).

### 3.5 Implications for the canonical two-Fyn contract (§0)

The canonical states that Onboarding Fyn "accepts multi-line information and SAVES AND WRITES it to the database so user information is persisted." The current code honours that promise for 4 entity types with known providers; for the other 12+ (goal, life_event, property, trust, will, POA, business_interest, chattel, family_member, liability, estate_gift, holding) and for unknown providers, multi-line user input silently drops entities. Today Onboarding Fyn literally fails to save what the user said — the LLM emits one tool call, the extractor doesn't cover the entity type, and nothing flags the drop to the user.

For the canonical to hold, the spec must require:
- Extractor coverage extends to all 18 entity types, OR
- Batch-shaped tool schemas (per `audit-synthesis.md §7.2`) replace the regex safety net.

Until one of these lands, the statement "Onboarding Fyn SAVES AND WRITES" is aspirational rather than observed.

---

## 4. Verified claims (doc ↔ code agrees)

| Claim | Location in docs | Code anchor | Status |
|---|---|---|---|
| 10-layer prompt, 3 static + 7 dynamic | system-map §4.1 | `app/Services/AI/AdvicePromptBuilder.php:51-120` (renamed from `SystemPromptBuilder.php` on the branch) | VERIFIED |
| Tool count in catalogue | system-map §7 (claimed 29) | **37 Anthropic tools** in `AiToolDefinitions::getTools()`; **33 xAI tools** in `XaiToolDefinitions::getTools()`. The "29" figure appears in neither file. | CORRECTED (v2) |
| `create_holding` / `capture_personal_details` / `capture_spouse_details` / `capture_dependants` presence | (derived) | Present in `AiToolDefinitions` only. xAI catalogue omits all four. Both providers include `list_records` and `set_expenditure`. | CORRECTED (v2) — earlier claim that xAI had the richer catalogue was inverted. |
| 22 query types | system-map §5.1 | `app/Constants/QuerySchemas.php` — 22 scalar query-type constants (in addition to array constants for groupings) | VERIFIED |
| 9 SSE event types | system-map §2.5 | `app/Traits/HasAiChat.php` yield statements emit exactly these 9 | VERIFIED |
| Token limits preview/trial/student/standard/family/pro = 100k/1M/300k/1M/1.5M/2M | system-map §12.1 | `HasAiGuardrails.php:30-37` | VERIFIED |
| MAX_TOOL_CALLS_PER_TURN = 5 | system-map §12.5 | `HasAiChat.php:44` | VERIFIED |
| Tool-use loop condition | system-map §12.5 | `HasAiChat.php:539` — `$hasToolCalls && $stopReason === 'tool_use' && $toolCallCount < self::MAX_TOOL_CALLS_PER_TURN` (line was 436 in v1) | VERIFIED at updated line |
| `update_record` blocklist is 2 fields (user_id, id) | verdict C3 | `CoordinatingAgent.php:3134` — `unset($safeFields['user_id'], $safeFields['id']);` (line was 2489-2490 in v1) | VERIFIED at updated line |
| `ConsentService` exists, NOT called in AiChatController or HasAiChat | verdict C5 | `app/Services/GDPR/ConsentService.php::hasConsent` exists; zero calls from chat flow (`grep -rn hasConsent app/Http/Controllers/Api/AiChatController.php app/Traits/HasAiChat.php` → zero matches) | VERIFIED |
| Audit log is file-based (`[AI-AUDIT]`), not DB | verdict C7 | `CoordinatingAgent.php:770` — `Log::channel('single')->info('[AI-AUDIT] Tool executed')` (line was 705 in v1; gating condition at line 768). Note: DB surfaces exist (`ai_messages`, `ai_advice_logs`) — audit is *partly* DB-backed. The `[AI-AUDIT]` file line is the tool-execution trail; the DB is the turn/message trail. | VERIFIED at updated line + nuance |
| Stale OpenAI config block | CSJTODO 0.17 | `config/services.php:34-38` | VERIFIED |
| Python sidecar has zero PHP callers (but HTTP routes are wired) | CSJTODO 0.16 | `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`, `AgentInternalController`, `AgentTokenAuth` all exist. `/api/internal/agent/*` routes are registered at `routes/api.php:1193-1199` and `AgentTokenAuth` middleware is registered at `app/Http/Kernel.php:81`. "Zero PHP callers" = no other PHP code invokes the controller internally. The HTTP surface is live — anything outside the repo (external worker, cron, adhoc curl) could call in. Sprint 0.16 deletion is safe *after* CSJ confirms no external caller. | VERIFIED with nuance |
| `ai_conversations.persona_state` migration | system-map §3 | `database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php` on persona-split | VERIFIED |
| `ai_messages.persona` migration | persona-split | `database/migrations/2026_04_22_000001_add_persona_to_ai_messages.php` | VERIFIED |
| `ai_tool_executions` table — absent | integrated-plan Sprint 0.18 | No migration matches this name | VERIFIED (it doesn't exist, which is the point) |

---

## 5. Invalidated claims (doc contradicts code)

| Claim | Location in docs | Code evidence | Status |
|---|---|---|---|
| Anthropic cache metrics NOT persisted | system-map §21 Q3, integrated-plan Sprint 1 | `HasAiChat.php:569-572` persists `cached_tokens` and `cache_hit_rate` to `ai_messages.metadata` (lines were 467-469 in v1) | **INVALIDATED** — metrics ARE persisted. Sprint 1 task is a no-op. |
| Admin UI for `AiAuditController` is MISSING | system-map §21 Q2 | `resources/js/components/Admin/AiAudit.vue` exists and is mounted in `AdminPanel.vue` | **INVALIDATED** — UI exists. |
| `feature/fyn-persona-split` closes the multi-entity issue | implied by integrated-plan §5.1 + §7 T14 | (v2 correction — see §3.2 above.) Entity extractor IS wired on both the onboarding director AND `FynPersonaInvoker::emitGapFillFromCaptureContext`. The real gap: extractor covers only 4 focuses (protection / savings / retirement / investment) with known-provider-only regex; the other 12+ entity types (goals, family, life events, property, trusts, wills, POAs, business interests, chattels, liabilities, estate gifts, holdings) get no gap-fill on either path. The duplication between the director and invoker emitters is real and is what Sprint 0.19 cleans up. | **PARTIALLY VALID** with corrected framing |
| `feature/fyn-persona-split` is 72 commits behind `main` | CSJTODO §Branch state | `git rev-list --count origin/feature/fyn-persona-split..origin/main` → 179 | **INVALIDATED** — 179, not 72. Rebase effort is ~2.6× worse than claimed. |
| Handoff state round-trip is verified | integrated-plan §3.4 test inventory and §T5 | Orchestrator code supports it; no explicit test for the round-trip found in `tests/Feature/AI/PersonaSplit/` (which has: CancelMidCapture, CaptureTimeout, ClassifierFastPath, CreatePOA, CreateWill, InlineCaptureFlow, KycGateFlow, PreviewMode) — no test named for round-trip of `pending_advice_question` | **PARTIALLY INVALIDATED** — claim stronger than test coverage supports. |
| Architecture collapse = delete `DataCapturePromptBuilder` + add `OnboardingChatDirector::handleInlineCaptureTurn` (Sprint 0.19) | integrated-plan §12.2 | `handleInlineCaptureTurn` method **does not exist** on persona-split. It's a proposed new method, not a refactor of an existing one. Realistic scope (v2, from three-pass review Pass 3): delete 1,238 LOC (invoker 518 + orchestrator 415 + registry 104 + DataCapturePromptBuilder 110 + config 91) plus ~1,000 LOC of tests; add ~1,000-1,200 LOC new (`AdviceFyn` + `handleInlineCapture` + dispatch changes + frontend + new tests). Net reduction ~500-800 LOC, not the claimed "~800 LOC deletion". | **UNDER-SCOPED** — effort 4-6 days, not 1-2. |

---

## 6. Claims requiring outside-code verification (flagged here for reviewers)

- **`AiAudit.vue` completeness** — exists, but does it actually expose tamper-evident audit (verdict C7)? No — it reads the existing DB rows (`AiMessage`, `AiConversation`, `AiAdviceLog`). Tamper-evidence needs a hash chain, not a viewer.
- **Meta Pixel / AWIN / Plausible** — all three confirmed present in code. Not verified: whether the Privacy Policy actually discloses them. `resources/js/views/Public/PrivacyPolicyPage.vue` not yet re-read at line level.
- **FCA regulated-advice boundary** — verdict C1 says no documented FCA analysis. Verifiable = absence of FCA doc in repo. Not verifiable from static code = whether the prompts' actual output crosses into "personal recommendation" territory. Needs prompt-output eval.
- **Anthropic Article 28 processor agreement** — cannot verify from repo.

---

## 7. Scope-creep / did-not-need-to-be-in-Fyn-scope

CSJTODO session 69 already notes CSJ removed these as out-of-scope for the Fyn audit:
- Meta Pixel, AWIN, FCM, Google DPA, Plausible general — app-wide compliance, not Fyn-specific.
- LPA creation rate KPI — dropped.
- Model currency (grok-4-1-fast-reasoning) — dropped, it's a unit-economics decision.

These have been correctly scoped out in the enterprise-verdict Pass 6 (Part M). But the earlier passes (Part F, K9) still contain references that bleed into Fyn-scope — reviewers should check whether Part M's corrections propagate to all downstream sections consistently.

---

## 8. Sprint 0 task-by-task realism check

| # | Task | Claimed effort | Reality check |
|---|---|---|---|
| 0.1 | Rebase `feature/fyn-persona-split` onto main | not quantified | 178-commit drift guarantees painful conflicts in `AppLayout.vue`, `CoordinatingAgent.php`, `router/index.js`, `AiToolDefinitions.php`, `Prompts/ComplianceRules.php`, `Prompts/FcaProcessInstructions.php`, `HasAiChat.php`, `StructuredResponseValidator.php`. Multiple modified files on both branches. Realistic: 0.5–1 day just for rebase + test-reruns. |
| 0.2 | Full Pest run post-rebase | not quantified | Realistic as stated. |
| 0.3 | Close PR #214 | 5 min | Trivial. |
| 0.5 | `update_record` per-entity field allowlist | 1 day | Realistic. Needs to cover 15+ entity types (Trust, Mortgage, FamilyMember, SavingsAccount, Pension, Investment, Property, Goal, LifeEvent, Protection, ...). |
| 0.6 | `delete_record` confirmation pattern | 4 hrs | Realistic. |
| 0.7 | `ConsentService::hasConsent` runtime check | 2 hrs | Realistic for the check itself. But: what happens when consent is withdrawn mid-conversation? What error UX? Not specified. |
| 0.8 | Sanitise user-controlled prompt fields | 4 hrs | Realistic for the regex. But also needs the sanitised value at write time to DB — which affects existing `/api/v1/profile` etc. endpoints. Scope may be bigger. |
| 0.16 | Delete Python sidecar | 1 hr | Realistic. |
| 0.17 | Remove stale OpenAI config | 5 min | Realistic. |
| 0.18 | Begin AI DB audit migration | 1 day | **Under-estimated.** Migration itself is easy. Migrating existing `[AI-AUDIT]` file log writes to DB inserts means touching `CoordinatingAgent::executeTool` AND every `handle*` method AND the read-audit viewer. Realistic: 1.5-2 days. |
| 0.19 | Collapse 3→2 personas | 1-2 days | **Design-dependent.** `handleInlineCaptureTurn` does not exist yet. This is new code, not just deletion. Plus: must ensure `AssetCaptureEntityExtractor` is called from the new inline path (closes the gap in §3.2 above). Realistic: 2-3 days incl tests incl the entity-extractor rewiring. |

---

## 9. Test coverage state

**CSJTODO claim:** 2,448 passing + 1 pre-existing flake (`AutoRiskCalculatorTest`).

**Persona-split tests directory:** `tests/Feature/AI/PersonaSplit/` contains:
- `CancelMidCaptureTest.php`
- `CaptureTimeoutTest.php`
- `ClassifierFastPathTest.php`
- `CreatePowerOfAttorneyToolTest.php`
- `CreateWillToolTest.php`

**Missing persona-split tests (inferred by reading the orchestrator / integrated plan §3.4):**
- `delegate_to_capture` handoff round-trip (advice → capturing → advice with `pending_advice_question` restored)
- `capture_complete` happy-path end-to-end
- Preview-user short-circuit in both advice and capturing states
- Multi-entity handoff (if advice Fyn delegates a multi-entity capture)
- Orchestrator state persistence across SSE disconnect
- Handoff-tool SSE stripping

Integrated-plan §3.4 claims "11 missing Feature tests" — the list above is consistent with that count. Sprint 2 picks this up. **Claim looks accurate.**

---

## 10. The three AI systems inventory (system-map §25)

1. **Fyn chat** (`app/Traits/HasAiChat.php` + `app/Agents/CoordinatingAgent.php`) — production-active, billed traffic, the core of the audit.
2. **`AIExtractionService`** (`app/Services/Documents/AIExtractionService.php:1-965`, 965 LOC) — document extraction. Uses Anthropic Vision and xAI Vision. System-map §23 states model is `claude-3-5-haiku-20241022` (stale). **Not verified line-by-line in this bundle** — reviewers should read this file to check whether the stale-model claim and the ~965 LOC figure hold.
3. **Python Agent SDK Sidecar** (`scripts/fynla_agent/`, `scripts/run_agent.py`, `app/Http/Controllers/Api/AgentInternalController.php`, middleware `app/Http/Middleware/AgentTokenAuth.php`, routes `routes/api.php:1193-1199`) — dead code per Phase-1 verification. No PHP callers, no cron, no jobs.

**Enterprise-verdict finding K11 — `AIExtractionService` gaps** — not directly verified here. The service is real and wired to doc-upload flows, so gaps in it matter. Should be read by the reliability reviewer.

---

## 11. Specific enterprise-verdict Critical findings — code-anchor check

| Finding | Claim | Code anchor | Status |
|---|---|---|---|
| C1 — No FCA analysis | No doc exists | `grep -r "FCA" docs/` finds no formal regulatory analysis doc. Prompts have "guidance not advice" language in Layer 3 (`app/Services/AI/Prompts/FcaProcessInstructions.php`) but no legal sign-off. | VERIFIED |
| C2 — no GDPR DPIA | No DPIA file | `grep -ri "DPIA\|data protection impact" docs/ resources/` — none found | VERIFIED |
| C3 — `update_record` over-exposure | 2-field blocklist | `CoordinatingAgent.php:3134` (was 2489-2490 in v1) | VERIFIED at updated line |
| C5 — no runtime consent check | Code not calling `hasConsent` in chat | Confirmed zero matches | VERIFIED |
| C6 — Article 9 health data | `health_status` etc. flow into system prompt via `buildFinancialContext` | `ProtectionPlanService.php:243` claimed to source `health_status` — **FILE DOES NOT EXIST on the branch** (v2 correction). Actual health-data sources confirmed: `RetirementActionDefinitionService.php:1606` (`$healthStatus = $protectionProfile?->health_status ?? null`) and `DecumulationPlanner.php:184` (`$healthStatus = $protectionProfile->health_status;` + `$isSmoker` at line 186). Whether this data reaches the system prompt via `orchestrateAnalysis` / `buildFinancialContext` needs per-field tracing — not confirmed in this bundle. | PARTIALLY VERIFIED with corrected source anchors |
| C7 — audit not tamper-evident | File channel, no hash chain | `Log::channel('single')` is append-only file, no cryptographic chain. Note: `ai_messages` and `ai_advice_logs` DB tables also capture system-prompt snapshots + tools-called arrays — those are DB-backed but row-mutable. Tamper-evidence requires chain+signing regardless of which surface is canonical. | VERIFIED with nuance |
| C8 — no DPIA | = C2 | | VERIFIED |
| C10 — read tools not audited | Only create/update/delete have `[AI-AUDIT]` writes | `CoordinatingAgent.php:768` (was 703 in v1) — `if (str_starts_with($toolName, 'create_') \|\| in_array($toolName, ['update_record', 'delete_record', 'update_profile']))` — reads are NOT logged. Nuance: most `create_*` tools return `fill_form` (form pre-fill), so the `[AI-AUDIT]` entry records a tool that may never produce a DB write — "tool executed" is the signal, not "record created". See §18 addendum. | VERIFIED at updated line + nuance |
| C11 — `AIExtractionService` gaps | (Details in verdict Part K) | Not re-verified here; reviewers should cross-check | UNVERIFIED |
| C14 — "no health data to third parties" policy contradiction | Privacy Policy says this but code sends health data to LLM | Privacy Policy wording not read at line level. Health data flow to LLM provisionally verified via C6. | PARTIALLY VERIFIED |

---

## 12. Things the docs omit that production should care about

- **xAI outage behaviour.** `app/Traits/HasAiGuardrails.php::getAiProvider` returns from cache (`ai_provider` key). If xAI is selected and returns 5xx, the chat loop in `app/Traits/HasAiChat.php::chat` surfaces the exception to the user as an SSE `error` event. There is no automatic failover to Anthropic. System-map §6 describes the selection but not the failure mode. Integrated plan's T20 ("provider failover") names it but grades it as Sprint 4 — fair.
- **Rate-limit on the SSE endpoint** is `throttle:20,1` (20/min/user). Not discussed in verdict as a DoS vector, though it is the relevant control.
- **`ai_advice_logs.user_data_snapshot`** is a JSON snapshot of the user's state at advice time. In GDPR SAR / erasure context, this persists potentially-sensitive data past the user's edit of underlying records. System-map §3.1 describes the column but does not flag the persistence-window implication. Verdict C18 (data subject rights) should address this.
- **The "first-message auto-title" (`app/Traits/HasAiChat.php::generateTitle`, line 704) sends raw user text to the LLM before any classifier gating.** If a user's first message contains prompt injection, it goes into `ai_conversations.title` and is surfaced on the conversation-list UI. System-map §21 Q8 ("title generation is raw user text") flags this — but the integrated plan doesn't schedule a fix.

---

## 13. Recommendations for reviewers

When reading the four docs, check each claim against this bundle first. Flag:
- Any reference to `OnboardingChatDirector` on `main` — it does not exist there.
- Any claim that persona-split closes the multi-entity issue — it closes it only for initial onboarding.
- Any Sprint 0/1 item that references already-landed behaviour (cache metrics, admin UI).
- Any effort estimate that treats a new-code task as a deletion task (Sprint 0.19).
- The 72-vs-178-commit drift understatement.

---

## 14. Addendum — Privacy Policy vs code (verified after initial bundle)

Privacy Policy file: `resources/js/views/Public/PrivacyPolicyPage.vue`.  Line numbers below are from that file on `feature/fyn-persona-split`.

**Direct quotes from the policy:**

- §5 (line 111): *"We do not use health data for any purpose other than your financial planning calculations. **We do not share health data with any third party.** You can delete this data at any time through your account settings."*
- §7 (line 132): *"We do not sell your personal data to any third party. We do not share your data with advertisers or marketing platforms. **We do not use third-party analytics or tracking services.** We do not allow any third party to use your data for their own purposes."*

**What the code actually does:**

| Third-party | Where loaded | Conditional? | Disclosed in Privacy Policy? |
|---|---|---|---|
| GetAddress.io | `resources/js/services/addressService.js` | Always | **YES** — `resources/js/views/Public/PrivacyPolicyPage.vue` §4 line 80 ("postcodes only"), §7 line 123 |
| **Anthropic** (document extraction) | `app/Services/Documents/AIExtractionService.php` | Always for documents | **YES (scoped)** — `resources/js/views/Public/PrivacyPolicyPage.vue` §7 line 124 lists Anthropic "for document data extraction"; chat use is NOT mentioned |
| **Anthropic** (chat) | `app/Traits/HasAiChat.php` — when `ai_provider = anthropic` | Admin-toggleable | **NO** — chat flow is not covered by §7's Anthropic disclosure |
| SiteGround (hosting) | (infrastructure) | Always | **YES** — §7 |
| mail.fynla.org | (infrastructure) | Always | **YES** — §7 |
| xAI (LLM) | `app/Traits/HasAiChat.php`, `app/Services/Documents/AIExtractionService.php` | Admin-toggleable | **NO** — not mentioned anywhere in the policy |
| Meta Pixel (`fbq`) — merchant ID `1878962689749080` | `resources/views/app.blade.php:80-89` (actual; v1 stated 81-91) | **UNCONDITIONAL** — fires on every page including authed areas | **NO** — §7 explicitly says "no advertisers or marketing platforms" |
| AWIN affiliate tracking | `resources/js/utils/awinTracking.js`, `app/Services/Marketing/AwinTrackingService.php`, `app/Jobs/FireAwinConversionJob.php`, migration `2026_04_15_153100_add_awin_tracking_to_payments_table.php`, `config/awin.php` | Env-gated (`VITE_AWIN_ENABLED`) | **NO** |
| Plausible | `app.blade.php:71-73` (actual; v1 stated 76-78) | Config-gated (`analytics.enabled` + `plausible_domain`) | **NO** — §7 says "no third-party analytics or tracking services" |

**Contradictions (v2 framing — clearer than v1):**

1. **§7 direct contradictions:** The policy says "we do not sell your personal data / share with advertisers or marketing platforms / use third-party analytics or tracking services". Contradicted by: Meta Pixel (unconditional — direct PECR Regulation 6 exposure on advertising cookies without consent), AWIN (env-gated — contradicts if enabled on production), Plausible (config-gated — contradicts if enabled).
2. **§7 under-disclosure of Anthropic chat use:** Anthropic is disclosed for document extraction only. Fyn's main chat flow sends user messages + system prompts to Anthropic too, under the same vendor. This is under-disclosure rather than non-disclosure, but still an UK GDPR Article 13-14 "information to be provided" defect.
3. **§7 xAI not mentioned:** xAI is admin-selectable as chat+vision provider; nowhere in the policy.
4. **§5 health data contradiction:** Policy says "we do not share health data with any third party". `health_status` and `smoking_status` flow into Protection + Retirement module analysis (`RetirementActionDefinitionService.php:1606`, `DecumulationPlanner.php:184`). Whether `orchestrateAnalysis` output is then woven into the system prompt sent to Anthropic/xAI — requires per-field trace through `AdvicePromptBuilder::buildFinancialContext`. If it does, §5 is contradicted. Not definitively verified in this bundle.
5. **Enterprise-verdict K3 count:** claimed "three undisclosed processors". Actual: **four clearly undisclosed** (xAI, Meta Pixel, AWIN-if-enabled, Plausible-if-enabled), plus **one under-disclosed** (Anthropic chat use). Disclosed: four (GetAddress, Anthropic for documents, SiteGround, mail.fynla.org). Verdict undercounts by at least 1 (probably 2 depending on how you count Anthropic). (v2 correction — v1 said "five processors of which GetAddress is the only disclosed one" — that was wrong on both ends.)

**Severity:** This is a hard factual contradiction between a public-facing legal document and production code. Under UK GDPR Articles 13-14 (information to be provided), misleading a data subject about processors is a supervisory-authority referral risk. PECR Regulation 6 (Meta Pixel cookies without consent banner) is a separate, cleanly-actionable ICO enforcement vector.

---

## 15. Addendum — `AIExtractionService` stale model verified

- `app/Services/Documents/AIExtractionService.php:19`: `private const ANTHROPIC_MODEL = 'claude-3-5-haiku-20241022';`
- File length: **965 LOC** (`app/Services/Documents/AIExtractionService.php:1-965`, matches system-map §23 claim exactly).
- Other path (xAI): uses `config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning')` — current.

Drift: chat uses `claude-haiku-4-5-20251001` (current), doc extraction uses `claude-3-5-haiku-20241022` (`app/Services/Documents/AIExtractionService.php:19`, 14 months stale). Different capabilities and pricing. System-map §23 flags this correctly.

---

## 16. Addendum — Stale OpenAI config block verified

`config/services.php:34-38`:
```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY', ''),
    'chat_model_pro' => env('OPENAI_CHAT_MODEL_PRO', 'gpt-5-mini-2025-08-07'),
    'chat_model_standard' => env('OPENAI_CHAT_MODEL_STANDARD', 'gpt-5-mini-2025-08-07'),
],
```

Confirmed stale — zero callers for any `openai.*` config key in the repo (`grep -rn "config('services.openai" feature/fyn-persona-split -- 'app/' 'routes/'` → zero matches). Sprint 0.17 removal is safe.

---

## 17. Addendum — `ai_advice_log` user-data snapshot

Not verified in original bundle — re-reading `AiAdviceLog` creation shows `user_data_snapshot` column captures income + expenditure + employment + marital at advice time (system-map §3.1 claim). This is a GDPR SAR / erasure concern: a user-record-edit does not propagate to past `ai_advice_logs.user_data_snapshot`. Verdict C18 (data-subject rights) should flag this explicitly; current verdict text does not.

---

## 18. Addendum (v2) — audit-log truthfulness problem

The `[AI-AUDIT]` file log at `app/Agents/CoordinatingAgent.php:770` fires inside `executeTool`, synchronously with the tool dispatcher — BEFORE any frontend action. `ai_advice_logs.tools_called` is populated from `$toolCallsSummary` at `app/Traits/HasAiChat.php:612` at end of turn, also BEFORE the frontend form submit lands. For the 11+ `create_*` handlers that return `['action' => 'fill_form', ...]` (see §5.2 of `audit-synthesis.md`), both log surfaces record "tool executed" / "tool called" for operations that may never persist:

- If the user closes the form modal — no DB record exists; audit says otherwise.
- If the form fails validation server-side — no DB record exists; audit says otherwise.
- If the network drops between frontend form submit and server — no DB record exists; audit says otherwise.

This is distinct from C3 (`update_record` over-exposure) and from C7 (tamper-evidence). It's an **audit-honesty** problem: the audit trail does not correspond to reality when the tool semantics are "prefill a form" not "write a record". The fix is either:

- Change `executeTool`'s audit-log site to record "tool dispatched" (not "tool executed") + follow up with a "record persisted" event emitted from the module API endpoints (e.g. `POST /api/savings-accounts` fires `AiAuditEvent::persisted(...)` on success).
- OR rename the tools from `create_*` to `prefill_*` so the audit semantics match the code semantics.

Belongs in Sprint 0.18 (audit-chain migration) scope.

---

## 19. Addendum (v2) — handoff-contract failure mode

`FynPersonaInvoker` (`app/Services/AI/FynPersonaInvoker.php`) strips `handoff` SSE events from the outbound stream and collects them in `$lastHandoff` (line ~145) for the orchestrator to interpret. If the LLM emits `delegate_to_capture` or `capture_complete` with a malformed payload (wrong argument shape, wrong tool-name casing, partial JSON truncated by SSE chunk boundary), `$lastHandoff` stays null. The orchestrator then treats the turn as "still capturing" and the user loops until `capture_max_turns` (6) fires the timeout.

No validator on the handoff payload exists in `app/Services/AI/StructuredResponseValidator.php` (which the branch extends by one line but not for this case). Low-cost, high-payoff defence: add shape validation at the invoker level and emit an SSE `handoff_malformed` event so the frontend can surface a retry.

Not currently in any Sprint scope; suggest adding to Sprint 0.19 (two-Fyn collapse) since handoff handling moves there anyway.

---

## 20. Addendum (v2) — persona_state vs onboarding_fyn_* reconciliation

Two state stores exist on the branch:

- `ai_conversations.persona_state` (migration `database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php`, written by `app/Services/AI/FynPersonaOrchestrator.php::persistState`) — `{current, pending_advice_question, capture_context, turns_in_capture}`.
- `users.onboarding_fyn_step`, `users.onboarding_fyn_path`, `users.onboarding_fyn_selection`, `users.onboarding_fyn_context` (migration `database/migrations/2026_04_15_090000_add_onboarding_fyn_state_to_users.php`) + `ai_conversations.onboarding_parked_facts` (migration `database/migrations/2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php`, written by `app/Services/Onboarding/OnboardingChatDirector.php`).

The two stores do not know about each other. `app/Http/Controllers/Api/AiChatController.php::sendMessage` routes based on `user.onboarding_completed` flipping true → director path retires, orchestrator takes over. The orchestrator reads `persona_state` which was backfilled to `{current: 'advice', ...}` by the migration; no inherited capture context.

For Sprint 0.19 (two-Fyn collapse): the migration that retires `FynPersonaOrchestrator` needs to either (a) clear `persona_state` on all conversations where the orchestrator's capturing state would be invalid post-collapse, or (b) keep `persona_state` readable by the new `handleInlineCapture` path. This is not named in the current Sprint 0.19 TODO.

---

## 21. Addendum (v2) — visible-handoff leak (direct violation of §0 canonical)

The canonical §0 states: *"The user never sees the handoff, or feels the switch, between the two states."* The current branch code directly violates this in two observable ways:

1. **`persona_state_change` SSE event is emitted on every advice↔capturing transition.** Source: `app/Services/AI/FynPersonaOrchestrator.php::personaStateChangeEvent` (lines 382-388 on the branch). Consumed in `resources/js/store/modules/aiChat.js:511-516` (the switch block):
   ```
   case 'persona_state_change':
       commit('SET_PERSONA_MODE', event.current);
       break;
   ```
   The mutation `SET_PERSONA_MODE` swaps `state.personaMode` between `'advice'` and `'capturing'`, and the chat panel reads this getter to swap the input placeholder text (observed: `AiChatPanel.vue` has `personaMode` as a computed prop and conditionally renders "Capturing your savings…" vs "How can I help?").

2. **Capturing pill rendered in the UI during capture mode.** The `resources/js/components/Shared/AiChatPanel.vue` and associated styles on the branch render a visible indicator when `personaMode === 'capturing'`. This is a direct visual signal to the user that the persona has changed.

Both of these must be removed for §0 to hold. The spec must state:
- `persona_state_change` SSE event is deleted OR kept as internal telemetry (never reaches the frontend Vuex store).
- The chat input placeholder is invariant regardless of handoff state.
- No capturing pill / capture-mode indicator is rendered.
- The `capture_complete` event MAY remain (it's a confirmation, not a persona switch indicator), but its message bubble styling must match normal assistant messages — no "capture" badge, no distinct border colour, no icon.

See also: `docs-three-pass-review.md` Pass 2 §C-5 (data-ownership: two state stores) and §C-6 (four layers of conversational state).

---

## 22. Addendum (v2) — missing billing / subscription tools (canonical §0 example not implementable today)

The canonical §0 cites as an example of Advice Fyn's scope: *"Where's my invoice? → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription."* This example requires tools that do NOT exist in the current 37-tool Anthropic / 33-tool xAI catalogue:

- No `get_subscription_status`
- No `list_invoices`
- No `get_current_plan`
- No `get_payment_history`

The `subscriptions` and `invoices` tables DO exist (verified via the revolutLive / payment work — see `app/Http/Controllers/Api/PaymentController.php`, `app/Http/Controllers/Api/WebhookController.php`, `app/Services/Payment/RevolutService.php`). The underlying data is there; no tool surfaces it to Advice Fyn.

Spec implication: add at minimum 3 new tools to the catalogue (both providers for parity):
- `get_subscription_status` — returns `{status, plan_name, trial_ends_at, current_period_end, next_charge_amount, is_cancelled}`.
- `list_invoices` — returns `[{invoice_id, issued_at, amount, status, pdf_url}]` with pagination.
- `get_current_plan` — returns `{plan_name, tier, price_gbp, features[]}`.

Catalogue becomes 40 Anthropic / 36 xAI after parity pass. Plus: `navigate_to_page` already exists, so the "navigate to the subscription page" half of the example is already supported.

See `resources/js/components/UserProfile/SubscriptionManagement.vue` + `app/Http/Controllers/Api/PaymentController.php` for the read surfaces these tools would wrap.

---

## 23. Addendum (v2) — memory model: three stores + one index

Per CSJ clarification, Fyn's memory model is **three DB-backed stores plus one index**, not four stores as earlier synthesis drafts suggested. The three stores all exist on the branch; the index does not.

**Store 1 — authoritative DB user state.** Columns and tables: `users.*` (first_name, surname, marital_status, dob, employment fields, income fields, `onboarding_*` state columns), `family_members` (spouse + dependants, bidirectional per `app/Services/Onboarding/SpouseLinkingService.php`), linked module tables (`savings_accounts`, `investment_accounts`, `dc_pensions`, `db_pensions`, `protection_*` policies, `properties`, `mortgages`, `trusts`, `wills`, `lasting_powers_of_attorney`, `estate_assets`, `estate_liabilities`, `estate_gifts`, `chattels`, `business_interests`, `goals`, `life_events`). **Exists.** Retrieval authoritative.

**Store 2 — current-turn parked facts.** `ai_conversations.onboarding_parked_facts` JSON column, written by `app/Services/Onboarding/OnboardingFactExtractor.php` on each user turn. Scoped to the live onboarding state; consumed by `OnboardingChatDirector` state handlers at commit points. **Exists.** Migration: `database/migrations/2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php`.

**Store 3 — current-conversation message history.** `ai_messages` rows for the conversation currently being served. Already in the prompt context via `app/Traits/HasAiChat.php::buildMessageHistory` (line 679). **Exists.**

**Index — absent on the branch.** There is no per-conversation summary / topic / entity / intent index today. `ai_conversations` columns: `id`, `user_id`, `title`, `status`, `model_used`, `metadata` (JSON scratch), `persona_state` (JSON state for the orchestrator), `onboarding_parked_facts` (Store 2), `message_count`, `last_message_at`, timestamps, soft-delete. No `summary`, no `topics`, no `entities_mentioned`, no `intents_stated`. No observer or job that populates such columns. No tool exposes a conversation-index scan to Fyn.

**Spec implication:** add one of the following, matching Advice Fyn's retrieval order (DB → parked facts → current conversation → index):

Option A — four new JSON columns on `ai_conversations`:
- `summary` (text) — one-paragraph human-readable summary.
- `topics` (JSON array of strings) — canonical topic tags (e.g. `["retirement", "annuities"]`).
- `entities_mentioned` (JSON array) — `[{type, id}]` records the conversation touched.
- `intents_stated` (JSON array of strings) — stated preferences that don't map to a DB column (e.g. `"wants to retire at 60"`, `"ethical-only investments"`).

Option B — new `ai_conversation_summaries` table with the above columns plus `conversation_id` FK. Kept separate if the summaries are expected to grow large or be indexed for text search.

Either way: populate via observer on `ai_messages` write (batched) OR at `STATE_DONE` transition OR via a short summariser LLM call run from a job. Expose to Advice Fyn via new tool `search_conversation_index` (returns matching `conversation_id` list + summary snippets). Advice Fyn loads the full conversation via existing `ai_messages` query only if a matched summary looks relevant.

This replaces the "intent memory" fourth store speculated in the prior synthesis draft. Simpler (no new retrieval class), lower write volume (one row per conversation rather than per-intent), explicit retrieval trigger (Fyn calls the search tool only when the known-facts block is silent), and makes cross-conversation surfacing in-scope for MVP via cheap index scan rather than requiring full-history replay.

Rubric-B scenarios `09-09 index-populated-on-close` and `09-10 cross-conversation-surface` cover this (see `fyn-rubrics.md §B`).

---

*Prepared for the five-agent audit — 24 April 2026.*
*Addenda 14-17 added after initial bundle. v2 addenda 18-23 added after the three-pass review + CSJ memory-model clarification — reviewers reading the final version get these automatically. Corrections throughout this bundle are marked with "(v2 correction)" at the point of change. §0 canonical added as source of truth for all downstream artefacts.*
