# Plan — Align audit docs + spec with canonical two-Fyn description

## Context

The three source documents (`audit-evidence.md`, `audit-synthesis.md`, `fyn-rubrics.md`) are the agreed foundation for the Fyn v2 spec, plan, and PRD. The user has now supplied an explicit canonical description of what Fyn's two states are in user-behavioural terms. That description is more complete than anything currently in the three docs:

- None of the three docs state the canonical two-Fyn contract at the top.
- `audit-synthesis.md §8.2` is the closest (CSJ decision 2, "persona count — TWO") but it's buried in section 8 and framed in terms of which classes to delete, not what each Fyn does for the user.
- `audit-evidence.md` only mentions "onboarding Fyn" in passing (§4, tool parity). `fyn-rubrics.md` does not distinguish the two Fyns in any dimension.
- None of the docs cover: bubble-driven onboarding, memory/resurface behaviour, resume-from-where-left-off, journey mapping by entry point, Advice Fyn's read-only semantic, "user never sees the handoff", Advice Fyn's full wiring to the recommendation engine / risk module / all module engines.

Until the canonical is present verbatim at the top of every subsequent artefact, every downstream writer (human or agent) is free to invent their own interpretation. That is the source of the ambiguity the user is reacting to.

## The canonical statement (to appear verbatim atop every doc, spec, plan, PRD, task list)

```
FYN HAS TWO STATES.

ONBOARDING FYN takes a user through the onboarding flow using bubbles for the user
to choose the path, and guides them through the flow they choose. It accepts
multi-line information and SAVES AND WRITES it to the database so user information
is persisted. It has memory: any additional information already entered is not
asked about again, but is resurfaced to the user at the right time to give a view
of intelligence. If a user leaves at any point in the conversation, the next time
they log in Onboarding Fyn picks up from where they left off (example only, not
the whole scope: "Good afternoon CSJ — last time we were busy entering your family
details, you told me about X. Do you want to continue from where we left off?"
Yes / No bubble). Journeys are mapped according to what the user wants and where
they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn
for any outstanding information needed to produce guidance. Onboarding Fyn is the
ONLY state that enters or edits information.

ADVICE FYN takes a user request, fetches the user's information, and answers that
request using the recommendation engine, the risk module, and every other module
or system in the app as needed. Examples only, not the whole scope:
  - "Where's my invoice?" → Advice Fyn checks subscription status and navigates
    to the subscription page, confirming the subscription.
  - "Should I contribute more to my ISA?" → Advice Fyn uses the recommendation
    engine to surface the guidance the engine produces and navigates to the
    portfolio page.
Advice Fyn covers tax optimisation (income tax, asset splitting between spouses,
etc.), and all other guidance across every module as per the financial planning
remit, classification system, recommendation engine, and all the investment,
retirement, protection, estate engines and modules. The ONLY thing Advice Fyn
does NOT do is enter or edit information — that is Onboarding Fyn's job.

THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH, between the two states.
```

This statement is the source of truth. Every doc, spec, plan, PRD, task list in this workstream renders it verbatim as the first section after the document title.

## Ambiguities / conflicts / omissions in the canonical statement — resolve before spec

The canonical is clear about intent. These are the implementation-level questions it does NOT answer. Flagging them here so the spec either resolves them explicitly or marks them as "open decision" in its §Open Decisions section.

### Handoff invisibility vs existing branch code (direct conflict)

A1. The current branch emits an SSE event `persona_state_change` that the Vuex store consumes to (a) swap the chat input placeholder ("How can I help?" ↔ "Capturing your savings…") and (b) surface a "capturing" pill in the chat UI. **Both are visible to the user.** The canonical says "the user never sees the handoff". → Spec must state: the `persona_state_change` SSE event, the placeholder swap, and the capturing pill are all REMOVED. Replace with silent state persistence only.

A2. Onboarding Fyn uses bubbles for structured choices during onboarding. If Advice Fyn hands off to Onboarding Fyn for a single-field inline capture post-onboarding, do bubbles appear? If yes, the user sees the switch. → Spec must state: bubbles are reserved for full-onboarding turns. Post-onboarding inline captures emitted by the `handleInlineCapture` path MUST NOT emit `quick_replies` SSE events — they use free-text prompts in the same conversational register as Advice Fyn.

A3. The current `AiChatPanel.vue` renders different message role types (`quick_replies`, `capture_complete`). A `capture_complete` message shows a record-card with "View" buttons — that's visibly different from an advice response. → Spec must decide: either (i) `capture_complete` UI stays (user sees confirmation but does NOT see a persona switch — that's arguably OK) or (ii) inline captures emit the same `advice_response` shape used by Advice Fyn. Recommendation: (i). The handoff is invisible; the RESULT of a save is still confirmable.

### Memory semantics (omission)

M1. The canonical says "any additional information already entered is not asked about, but resurfaced to the user at the right time". Four data stores could back "memory":
  - User profile columns + related tables (`users.*`, `family_members`, `protection_*`, etc.)
  - Conversation history (`ai_messages`)
  - Parked facts (`ai_conversations.onboarding_parked_facts`)
  - New "intent memory" — facts the user stated that don't map to a DB column ("I want to retire at 60 if I can").

  Spec must state: Onboarding Fyn's state machine prompts and Advice Fyn's prompts check ALL of these before asking a question. Specifically: the prompt builder injects a "known facts" block that enumerates populated fields from every relevant source, and the LLM is instructed never to ask for a populated field.

M2. "Resurfaced at the right time" — WHICH time? The spec must state the trigger. Recommendation: Advice Fyn surfaces a known fact when it is an input to the current query type's recommendation engine call. Example: user asks "can I afford to buy a £500k house?" → Advice Fyn's query type is `goals_affordability` → required inputs include current net worth, monthly surplus, existing goals, life events. The surfaced facts are those inputs, labelled as "you told me" in the response. No general "let me remind you of random things" surfacing.

M3. Cross-conversation memory — does Advice Fyn reference a prior conversation? e.g. user asked about IHT last week, this week asks "what about my estate?" — does Advice Fyn say "last time we discussed X; since then Y has changed"? Current code persists full `ai_messages` history but `summariseToolResult` strips detail. Spec decision needed: for MVP, scope memory to within-conversation (the `ai_messages` already loaded) + persisted DB state. Explicit out-of-scope: cross-conversation summarisation.

### Resume-from-where-left-off (partial coverage on branch, needs spec codification)

R1. Current code: `users.onboarding_fyn_step`, `users.onboarding_fyn_path`, `users.onboarding_fyn_selection`, `users.onboarding_fyn_context` + `ai_conversations.onboarding_parked_facts`. Already persists enough to resume. But: the branch does NOT today emit a natural-language resume greeting; it emits a `resume` SSE event that routes the frontend to the conversation. → Spec must state: Onboarding Fyn's first turn after reconnect emits (i) the natural-language "last time we were doing X" greeting, (ii) a Yes/No bubble, (iii) a deterministic state-machine resume based on the answer.

R2. The "family details" example in the canonical implies the greeting references the SPECIFIC state (e.g. "family details"). → Spec must state: `OnboardingStateMachine` state records each carry a `resume_summary` string (e.g. "entering your family details", "capturing your savings accounts"). The director reads the current step's `resume_summary` when building the greeting.

R3. Advice Fyn resume — does Advice Fyn greet the user on login? Current code: no greeting, user sends first message. → Spec should state: Advice Fyn does NOT auto-greet on login (user initiates). Only Onboarding Fyn resumes, because only Onboarding has a multi-turn state to resume.

### Journey mapping by entry point (partial coverage)

J1. Current journeys in `OnboardingStateMachine::STATE_JOURNEY_SELECTION`: budgeting, goals, protection, retirement — 4 options. Labels match `lifeStageConfig.js` landing-page CTAs.

J2. The canonical says "where they enter into the onboarding from". Current code supports `?from=fyn` query param. Does the spec add pre-selection by entry point? e.g. landing-page "Planning Your Future" CTA → onboarding starts AT the retirement journey, skipping `STATE_PATH_CHOICE`. → Spec must state explicit mapping: entry_source query param → pre-selected journey. Candidates: `from=retirement`, `from=savings`, `from=protection`, `from=goals`, `from=direct` (no pre-selection; starts at path_choice).

J3. "Where they enter from" could also mean device (mobile vs web). Recommendation: out of scope for the behavioural spec — device differences are a UX concern.

### Advice Fyn's scope (omission)

S1. The canonical says Advice Fyn covers "all other relevant advice for all modules as per the financial planning remit". What is IN remit vs OUT of remit?
  - In remit: 7 modules (Protection, Savings, Investment, Retirement, Estate, Goals, Tax) + cross-module coordination + subscription / billing / invoice.
  - Out of remit spec candidates: general financial education (wikipedia-style), legal advice, medical advice, mental health, anything requiring a regulated financial adviser.
  → Spec must state a closed list of IN-remit query types (likely the existing 22 `QuerySchemas` constants plus new subscription/billing types) and a policy for out-of-remit: "I can't help with that; here's how to reach human support".

S2. "If a user asks about their invoice" — requires tools that DON'T currently exist. The 37-tool list has no `get_subscription_status`, `list_invoices`, `get_current_plan`. → Spec must add a new tool group "Billing / Subscription" with, at minimum: `get_subscription_status`, `list_invoices`, `get_current_plan`. Adds ~3 to the catalogue (40 Anthropic / 36 xAI after parity).

S3. "Advice Fyn uses the recommendation engine to give the guidance that is in the system" — is the recommendation engine output the ONLY source of advice text, or can Advice Fyn also render raw numeric data (net worth, tax liabilities) without an engine wrapping? → Spec must state: Advice Fyn has two response modes: (a) **factual** — fetch data + return, no engine involvement (e.g. "what's my net worth?" → `NetWorthService` → display); (b) **recommendation** — engine wrapping required for any interpretive content (e.g. "should I contribute more?" → `orchestrateAnalysis` → display engine output). Advice Fyn selects mode via query classification.

S4. Tax optimisation specifically: covered by `TaxOptimisationAgent` + `Investment/Recommendation/SpouseOptimisationService` + `Investment/Tax/` services. The canonical mentions "income, asset splitting between spouses". Spec must state: tax-optimisation queries route through `TaxOptimisationAgent::analyze` → same recommendation engine pipeline.

S5. "No interpretation from the model" — does this mean Advice Fyn's response text is LITERALLY the engine's `recommendation_text` + `rationale` fields, or that Advice Fyn's LLM composes prose around engine outputs but adds no interpretive opinions? → Spec recommendation: the model is allowed to compose coherent prose (for readability) but every factual / interpretive claim must cite an engine output field or a `personalised_context` bullet. Falsifiable test: for a given scenario, assert that every non-trivial sentence in Advice Fyn's response maps to a source field in the engine's output.

### Handoff mechanics (partial coverage, needs codification)

H1. Canonical: "Advice Fyn hands over to Onboarding Fyn for outstanding information". The spec must state:
  - Advice Fyn's query flow: (i) classify query; (ii) call `DataReadinessService` (exists) to identify missing required inputs; (iii) if any missing, emit `delegate_to_capture` with a `CaptureContext` listing required fields; (iv) else proceed to engine + response.
  - Onboarding Fyn receives the `CaptureContext`, runs `handleInlineCapture`, persists via direct-write services, emits internal `capture_complete`.
  - Control returns to Advice Fyn automatically (no user turn). Advice Fyn re-runs steps (i)–(iv). If `DataReadinessService` still flags missing, Advice Fyn SHOULD tell the user "I still need X — do you want to add it now?" rather than loop infinitely.

H2. Canonical: "User never sees the handoff". From the user's perspective, the exchange must read as one continuous Advice Fyn conversation. That means:
  - No "switching to capture mode" preamble
  - Capture prompts phrased in the same conversational register as Advice Fyn
  - Capture acknowledgement ("got that — now, back to your question…") reads as Advice Fyn continuing, not a separate actor
  - No SSE event that the frontend could use to visually distinguish the capture phase

H3. Failure modes the canonical doesn't address:
  - User provides invalid capture data (email malformed, date nonsensical). Spec decision: Onboarding Fyn re-prompts with a specific validation message; if user refuses to provide after 3 attempts, Advice Fyn returns "I can't answer that without X; here's what I CAN tell you" with a graceful degraded response.
  - User abandons mid-capture (closes chat). `handleInlineCapture` times out after `capture_max_turns`; persona_state cleared; next user turn resumes as Advice Fyn.
  - Multi-field capture mid-advice (advice needs 3 fields, user only supplies 2). Spec decision: Onboarding Fyn loops within the inline capture for remaining fields, still no visible handoff.

### Read-only vs write semantic (direct conflict with current code)

W1. Canonical: "The only thing [Advice Fyn] does not do is enter or edit information — that is done through Onboarding Fyn." But current code wires `update_record`, `delete_record`, `update_profile`, `set_expenditure` into the `advice` persona's `allowed_tools` list via `config/fyn_personas.php`? Check the current config:
  - Actually the current config gives `update_record`, `delete_record`, `set_expenditure`, `update_profile` to the `data_capture` persona, not `advice`. So the current branch already aligns with the canonical at the tool-allowlist level. ✅
  - But: after the two-Fyn collapse, write tools must ONLY be callable from the Onboarding Fyn code path. Spec must state: `AdviceFyn` class's tool list excludes every tool that mutates DB state. Period.

W2. Edge case: what about tools that compute-and-persist (e.g. `create_what_if_scenario` — writes a scenario row)? Is that a write, or a computed artefact? → Spec decision: `create_what_if_scenario` is a computed-artefact write with no user-facing data implications; Advice Fyn MAY call it. Same for any tool whose write is an ephemeral analytics artefact. Concretely list these in the spec.

### Recommendation engine wiring (partial coverage, needs explicit invariant)

E1. Advice Fyn calling the engine on every turn is expensive (`orchestrateAnalysis` calls all 7 agents). The spec should state: Advice Fyn calls `orchestrateAnalysis` ONLY for query types classified as `holistic_*` or `cross_module_*`. For module-scoped queries, it calls the single relevant agent's `analyze()` method. For pure-factual queries (e.g. "what's my net worth?"), it calls the module service (e.g. `NetWorthService::getOverview`) with no engine involvement.

E2. Caching: `orchestrateAnalysis` output is cached at `holistic_plan_{userId}` for N seconds (check current TTL). Advice Fyn must respect this cache but MUST invalidate it when Onboarding Fyn persists new data mid-conversation. Spec must state: the direct-write services (SavingsAccountService::create etc.) invalidate the holistic cache, and Advice Fyn's next call picks up fresh data.

E3. Recommendation-engine response shape — the spec must cite the existing return shape (`ranked_recommendations`, `conflicts`, `cashflow_allocation`, `cross_module_strategies`, `summary` + `HolisticPlanner`'s `executive_summary`, `net_worth_projection`, `risk_assessment`, `action_plan`) and state that Advice Fyn's `advice_response` SSE event is a projection of this shape into a chat-renderable form. No new engine invented.

### System-level messages that are neither Onboarding nor Advice

U1. Token limit reached, consent required, preview-mode short-circuit, maintenance notice — these are system emissions, not either Fyn. Spec must state: system messages are always allowed and do NOT count as a handoff violation. They're emitted by `AiChatController` middleware / early-return paths, not by either Fyn's chat loop.

## Document-by-document review — current coverage and required edits

### audit-evidence.md

**Current coverage of the canonical:**
- Silent on behavioural contract. Frames everything in code-archaeology terms.
- §3.2 (v2 corrected) explains the gap-fill wiring across both paths.
- §20 addendum flags the two state stores (`persona_state` vs `onboarding_fyn_*`) — technical reality, not user contract.
- Mentions "advice Fyn" and "onboarding Fyn" only incidentally.

**Required edits:**
1. Add a new `§0 — Canonical two-Fyn contract` at the very top, ABOVE §1 Branch state. Verbatim rendering of the canonical statement.
2. Add a new subsection at end of §3 (Multi-entity): `§3.5 — Implications for the canonical contract`, explaining that extractor coverage gaps mean Onboarding Fyn cannot today honour the "multi-line information saving" promise for 12+ entity types.
3. Add a new addendum `§21 (v2) — Visible-handoff leak`, documenting that `persona_state_change` SSE event + capturing pill directly violate the canonical "user never sees the handoff". Include file:line refs (`resources/js/store/modules/aiChat.js:511-516`, `AiChatPanel.vue` capturing-pill render).
4. Add `§22 (v2) — Missing billing/subscription tools`, documenting that the canonical's invoice example requires 3 new tools not currently in the catalogue.

### audit-synthesis.md

**Current coverage of the canonical:**
- §1 #15: "Two-Fyn intent is architecturally defensible" — good but buried.
- §5.6: "three-persona vs two-persona" terminology reframing — good but still in code terms.
- §8.2: CSJ decision 2, "persona count TWO". Mentions the architectural split but nothing about:
  - bubbles
  - memory / resurface
  - resume-from-where-left-off
  - journey mapping by entry point
  - handoff invisibility
  - Advice Fyn's read-only semantic
  - recommendation engine wiring specifics

**Required edits:**
1. Add new `§0 — Canonical two-Fyn contract` at top, ABOVE §0 Headline. Verbatim.
2. Rewrite §8.2 to extend the CSJ decision with the canonical's full behavioural scope (memory, resume, bubbles, invisibility, read-only). Link to the Open Decisions list in §9.
3. Add a new `§5.7 — Canonical gaps in current architecture`, listing every point where the current branch code violates the canonical (persona_state_change visibility, inline capture bubble risk, missing billing tools, absent resume-greeting, absent memory-surface in prompts).
4. Add new entries in §9 (Recommendations) mapping each ambiguity A1–U1 above to a spec-resolution item.

### fyn-rubrics.md

**Current coverage of the canonical:**
- Effectively silent. Rubric A dimensions don't distinguish Onboarding Fyn from Advice Fyn. Rubric B scenario categories mention "handoff round-trips" but don't frame them in canonical terms.

**Required edits:**
1. Add new `§0 — Canonical two-Fyn contract` at top, ABOVE the existing intro.
2. Extend Rubric A with two new dimensions:
   - `D11 — Handoff invisibility` (Level 0: persona_state_change visible ... Level 4: verified via Playwright that no DOM/a11y change occurs during handoff).
   - `D12 — Memory coherence` (Level 0: prompts ask for already-entered facts ... Level 4: full cross-conversation memory with surfacing rules).
   Re-weight: 10 dims × 5 levels = /40 becomes 12 × 5 = /60. Adjust scoring bands. Alternative: keep /40, fold handoff invisibility into D5 (LLM safety) and memory into D9 (observability) — cleaner. Recommendation: fold into existing dims with explicit sub-criteria, keep /40.
3. Extend Rubric B scenario catalogue:
   - New category `09-canonical-behaviour` (~8 scenarios): onboarding-path-choice-to-done, onboarding-resume-after-disconnect, onboarding-memory-no-repeat-ask, advice-factual-net-worth, advice-recommendation-engine-route, advice-invoice-subscription-check, advice-handoff-invisible-capture, advice-read-only-tool-list.
   - Total scenario count rises from 65 to ~73.
4. Update the "How to use" section to state: every PR that touches `AdviceFyn`, `OnboardingChatDirector`, or any related tool handler must score itself against D11 and D12 in the PR description.

## Edit plan (to run once plan is approved; no edits during plan mode)

### Phase 0 — Add canonical preamble to every artefact

Write the canonical statement as a single Markdown snippet. Insert verbatim at the top of each:
- `audit-evidence.md`
- `audit-synthesis.md`
- `fyn-rubrics.md`
- (forthcoming) `fyn-v2-spec.md`
- (forthcoming) PRD, implementation plan, task list
- repo copies + vault copies

No deviation — the same literal text on every file. A shell-script helper `scripts/insert-canonical.sh` (run once) that reads the canonical from a master source (`docs/canonical/fyn-two-states.md`) and injects it into the top of every file in a tracked list.

### Phase 1 — audit-evidence.md surgical edits

Inserts and rewrites as per the "Required edits" list above. Line anchors for existing sections will drift; accept.

### Phase 2 — audit-synthesis.md surgical edits

As above.

### Phase 3 — fyn-rubrics.md surgical edits

Fold D11/D12 criteria into D5 and D9 (preferred), extend Rubric B scenario catalogue, update "How to use" section.

### Phase 4 — Write `fyn-v2-spec.md`

Structure (as previously proposed, now anchored in canonical):

```
§0  Canonical two-Fyn contract (verbatim)
§1  Current system (grounded in code, anchored file:line) — two Fyns as-built vs
    canonical; tool surface; frontend SSE event + component inventory; rubric-A
    current 4-5/40.
§2  Target invariants — each invariant is a falsifiable observable property.
    §2.1  Two-Fyn split (dispatch, class boundaries)
    §2.2  Onboarding Fyn — bubbles, multi-line writes, memory, resume, journeys
    §2.3  Advice Fyn — read-only, engine wiring, response modes
    §2.4  Handoff invisibility — SSE events, UI invariants
    §2.5  Tool semantics — direct-write, observer fire, audit honesty
    §2.6  Read completeness
    §2.7  Provider parity
    §2.8  Multi-entity coverage
    §2.9  Reliability invariants
    §2.10 Compliance invariants
    §2.11 System messages (token limit, consent, preview)
    §2.12 Rubric-B eval invariants
§3  Delta register (Sprint 0 / 1 / 2 / 3 / 4)
§4  Out of scope for the spec (PRD territory)
§5  Open decisions that block implementation (A1–U1 ambiguities distilled)
§6  Rubric-B scenario catalogue stub
```

Every §2 invariant takes the form:
```
Invariant §2.X.Y: <property statement>
Falsifiability test: <command or Playwright script that returns yes/no>
Acceptance criterion: <what PASS looks like>
```

Target length: 1200–1800 lines. Location: `/Users/CSJ/Desktop/fynla/April/April24Updates/fyn-v2-spec.md` + vault copy.

## Spec approach (invariant sketches to show calibration)

Examples of falsifiable invariants derivable from the canonical:

- **§2.1.1 Dispatch:** `AiChatController::sendMessage` routes to exactly one of: `OnboardingChatDirector` (when `user.onboarding_completed = false`), `AdviceFyn` (otherwise). No third code path. Falsifiability test: `grep -rn "new Fyn\|->dispatch\|route to" app/Http/Controllers/Api/AiChatController.php` returns exactly two dispatch branches plus one early-return for system messages.

- **§2.4.1 No visible persona change:** The response SSE stream for a conversation that contains a handoff turn emits ZERO `persona_state_change` events. Falsifiability test: scripted Playwright session triggers advice→capture→advice handoff; assertion `events.filter(e => e.type === 'persona_state_change').length === 0`.

- **§2.4.2 No capture-phase bubbles:** For any turn in which Advice Fyn delegated to Onboarding Fyn, the emitted SSE stream contains ZERO `quick_replies` events. Falsifiability test: as above, assert `events.filter(e => e.type === 'quick_replies').length === 0` during the capture sub-turn.

- **§2.2.4 No re-ask:** Onboarding Fyn's state prompts never ask for a field whose DB value is non-null. Falsifiability test: seed a user with `marital_status = 'married'`; start onboarding; assert the `base_spouse` state's prompt text references the user's existing marital_status (or is skipped entirely).

- **§2.3.3 Engine is only source of interpretation:** For any Advice Fyn response of classification `recommendation_*`, every sentence that contains interpretive language ("suggest", "consider", "recommend", "you could", "worth looking at") can be mapped to a source field in `orchestrateAnalysis()` output or an `ActionDefinition` template. Falsifiability test: Mode-2 rubric-B eval runs regex on response text against an allowlist of source fields; fails if any unmapped interpretive sentence is found.

- **§2.2.6 Resume greeting:** For any user with `users.onboarding_completed = false` AND `users.onboarding_fyn_step != null` AND more than 5 minutes since last `ai_messages.created_at`, the next chat open emits a greeting whose text contains the `resume_summary` from the current step and a Yes/No bubble. Falsifiability test: Playwright — disconnect at state `base_dependants_detail`; wait 6 minutes; reconnect; assert the first SSE `quick_replies` event's `prompt_text` contains the string "family details" (from the state's `resume_summary`).

## Verification (how to confirm the alignment once the edits ship)

1. **Canonical consistency grep:**
   ```sh
   grep -l "FYN HAS TWO STATES" April/April24Updates/*.md docs/canonical/*.md
   ```
   Must return every doc in the workstream.

2. **No lingering three-Fyn language:**
   ```sh
   grep -rn "three persona\|three-persona\|orchestrator Fyn\|data_capture persona" April/April24Updates/
   ```
   Must return only historic v1 artefacts or the v2 retraction markers.

3. **Spec falsifiability check:**
   Every `Invariant §2.X.Y` in the spec has a `Falsifiability test:` line AND an `Acceptance criterion:` line. Shell check:
   ```sh
   awk '/^Invariant/ { i++ } /^Falsifiability test:/ { f++ } /^Acceptance criterion:/ { a++ } END { if (i != f || i != a) exit 1 }' fyn-v2-spec.md
   ```

4. **Rubric coverage:**
   Rubric A has dimensions covering every §2 invariant category. Manual cross-check post-edit.

5. **Eval harness skeleton:**
   `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/` directory exists with ≥8 scenario files named per the canonical use cases.

6. **User review gate:**
   The user reads the canonical statement in each file and confirms word-for-word match with the description they supplied. If any word drifts, the workstream stops until the canonical is re-locked.

## Critical files to touch (when edits are authorised)

- `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-evidence.md` — add §0, §3.5, §21, §22
- `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-synthesis.md` — add §0, rewrite §8.2, add §5.7, extend §9
- `/Users/CSJ/Desktop/fynla/April/April24Updates/fyn-rubrics.md` — add §0, extend D5+D9 criteria, add scenario category 09, update "How to use"
- `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/*.md` — mirror of above
- `/Users/CSJ/Desktop/fynla/docs/canonical/fyn-two-states.md` — new file, master copy of canonical (single source for the script)
- `/Users/CSJ/Desktop/fynla/April/April24Updates/fyn-v2-spec.md` — new file, the spec itself
- `/Users/CSJ/Desktop/fynla/April/April24Updates/docs-three-pass-review.md` — add §0 canonical (for consistency; it's a live artefact in the same folder)
- `/Users/CSJ/Desktop/fynla/April/April24Updates/code-vs-review-report.md` — add §0 canonical (ditto)

## Reuse inventory (existing code to cite in the spec, not re-design)

- `CoordinatingAgent::orchestrateAnalysis` — recommendation engine entry
- `HolisticPlanner::createHolisticPlan` — holistic plan shape (executive_summary, net_worth_projection, risk_assessment, action_plan)
- `RecommendationsAggregatorService` + `RecommendationPersonaliser` — per-recommendation personal context
- Module agents: `ProtectionAgent`, `SavingsAgent`, `InvestmentAgent`, `RetirementAgent`, `EstateAgent`, `GoalsAgent`, `TaxOptimisationAgent`
- `Investment/Recommendation/ContributionWaterfallService` — allocation waterfall (LISA → ISA → pension → ...)
- `SpouseLinkingService` — creates User + sends `SpouseAccountCreated` / `SpouseAccountLinked` email; already direct-write
- `HouseholdProvisioner` — household_id backfill
- `ActionDefinition` models + services per module — recommendation template library
- `RecommendationTracking` — pending→in_progress→completed lifecycle
- `DataReadinessService` (Investment/Recommendation/) — pattern for "what inputs does this recommendation need"
- `OnboardingStateMachine` states — path_choice, journey_selection, base_personal, base_spouse, base_dependants, base_dependants_detail, profile_review_family, profile_review_expenditure, asset_capture, add_more, done
- `OnboardingChatDirector` — state dispatch; extend with `handleInlineCapture`
- `AssetCaptureEntityExtractor` — 4-focus regex extractor; needs coverage extension
- `HandoffContract` — `delegate_to_capture`, `capture_complete` constants (retain)
- `CaptureContext` VO — retain
- `HasAiChat` trait — chat loop, tool dispatch, token accounting; split appropriately between AdviceFyn and OnboardingChatDirector
- `QuerySchemas` — 22 query types, classification constants; will likely grow
- `NetWorthService` — factual net-worth query
- `ConsentService` — already exists, needs runtime wiring

## Next action after plan approval

1. Create `/Users/CSJ/Desktop/fynla/docs/canonical/fyn-two-states.md` with the verbatim canonical.
2. Add canonical preamble to all three existing docs + `docs-three-pass-review.md` + `code-vs-review-report.md`.
3. Mirror to vault.
4. Make surgical edits per Phases 1–3 above.
5. Write `fyn-v2-spec.md` per §Spec approach.
6. Verification per §Verification section.
7. Hand to writing-plans for implementation-plan authoring.
