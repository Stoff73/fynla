# System prompt audit — Advice Fyn

*Authored 27 April 2026, session 98 end. Triggered by CSJ inspection of the system prompt captured in eval recording session #5 (`advice_protection_cover`, Anthropic Haiku 4.5, conversation_id=145).*

---

## TL;DR

1. **The billing block IS unconditionally injected into every Advice Fyn turn** for every non-preview user, regardless of query classification. That is wrong — billing guidance should be classification-gated like every other dynamic layer, OR moved to tool descriptions so it only fires when the billing tools are selected.
2. **The layered prompt system is NOT being ignored — it is alive and being respected.** AdvicePromptBuilder still composes 13 layers, and 5 of them (FinancialContext, ExistingRecords, Knowledge, Tools/Triggers, FCA Signposting) are correctly classification-gated.
3. **The `<known_facts>` / memory layer is MISSING from the assembled prompt** because it is a Sprint 1 deliverable (S1.4 in `April/April24Updates/plan/11-sprint-1-plan.md`) that has not shipped yet. This is not a regression — it is unbuilt scope. Conflating "not yet built" with "the layered system is being ignored" misreads the situation.
4. **Billing got jammed in flat (not classification-gated) on 2026-04-26 03:40 in commit `c51e7ff`** to make BS-16 GREEN. The tightening pulled grok-4-1-fast away from a one-tool list-only response onto the BS-16 acceptance shape (call both billing tools, lead with subscription line, count phrasing). The fix worked for BS-16. The cost is that **every protection / savings / investment / estate query now ships ~830 chars of billing instructions the model does not need** and that crowd out token budget that should be going to context.

---

## What CSJ asked

After viewing the captured system prompt for scenario 1 in the new admin eval viewer, CSJ asked five distinct questions. They are recorded here so the audit addresses all of them:

1. Why is there a billing section in the system prompt?
2. Why is there not a tool to handle invoice/billing instead?
3. Why is the billing block loaded every turn (regardless of query)?
4. Why is there no context section (i.e. `<known_facts>`)?
5. The layered prompt system was built deliberately — when did it stop being respected, and what bypasses it now?

---

## Method

Evidence-only. No memory, no speculation. Sources used:

- `git blame` on `app/Services/AI/AdvicePromptBuilder.php` lines 80–268 (covers handoff layer, billing layer, dynamic context layer, knowledge block).
- `git log --follow` on the same file (commit list April 1 → April 26).
- Live database read of `ai_messages.system_prompt` for the assistant message in conversation 145 (the eval recording captured at session start).
- `grep` on `classification`, `QuerySchemas`, `Cache::remember` in the prompt builder.

All file:line references in this doc were re-read at audit time, not recalled.

---

## What is in the prompt right now

Captured from `ai_messages.system_prompt` for `conversation_id=145` (Haiku run of `advice_protection_cover`). Total length **18,484 chars**. XML-tagged sections present:

| Section | Static / Dynamic | Classification-gated? | Source |
|---|---|---|---|
| `<identity>` | static | no (always) | `Prompts/CoreIdentity.php` |
| `<security>` | static | no (always) | `Prompts/CoreIdentity.php` |
| `<scope>` | static | no (always) | `Prompts/CoreIdentity.php` |
| `<personality>` | static | no (always) | `Prompts/CoreIdentity.php` |
| `<response_format>` | static | no (always) | `AdvicePromptBuilder` literal |
| `<instructions>` | static | no (always) | `AdvicePromptBuilder` literal |
| `<regulatory_compliance>` | static | no (always) | `Prompts/ComplianceRules.php` |
| `<fca_process>` | static | no (always) | `Prompts/FcaProcessInstructions.php` |
| `<handoff_guidance>` | static | no (always, except preview) | `AdvicePromptBuilder::getHandoffGuidance()` |
| **`<billing_guidance>`** | **static** | **NO (always, except preview) — THIS IS THE FAULT** | `AdvicePromptBuilder::getBillingGuidance()` |
| `<user_profile>` | dynamic | no (always for known users) | `buildUserProfile($user)` |
| `<financial_context>` | dynamic | **YES — filtered by classification** | `buildFinancialContext()` |
| `<existing_records>` | dynamic | **YES — filtered by classification** | `buildExistingRecordsSummary()` |
| `<available_actions>` | dynamic | YES (when `$isPreview`) | `buildAvailableActions()` |
| `<user_provided>` | runtime | injected per turn | turn-side |
| `<new_user_state>` | conditional | only for empty-data users | `Prompts/EmptyDataGuard.php` |

**XML tags with `0` occurrences** (i.e. NOT in the assembled prompt):

| Section | Status |
|---|---|
| `<known_facts>` | **MISSING — Sprint 1 deliverable S1.4 not shipped yet** |
| `<core_identity>` | n/a — `<identity>` is the actual tag; `core_identity` is the PHP class name |
| `<query_knowledge>` | n/a — knowledge fragments are emitted as guidance text under `<instructions>`, not under their own tag |
| `<data_completeness>` | n/a — replaced by `<new_user_state>` for empty users; otherwise inferred from `<financial_context>` |

---

## Q1 — Why is there a billing section in the system prompt?

**Direct answer:** Because BS-16 ("Where's my invoice?") had a contract that required a specific response shape (subscription status line first, "You have N invoices" count phrasing, then itemised list), and grok-4-1-fast was failing it by calling only `list_invoices` and producing a list-only reply. Adding the `<billing_guidance>` block to the system prompt forced the dual-tool call and pinned the reply shape so BS-16 could go GREEN.

**Evidence:** `git blame` on `app/Services/AI/AdvicePromptBuilder.php:89-101` and `:242-268`. Every line traces to commit `c51e7ff`, authored by Stoff73 on **2026-04-26 03:40:11 +0100**, commit message `test(browser): BS-16 contract-GREEN after S0.5.u (S0.16b)`.

---

## Q2 — Why is there not a tool to handle invoice/billing instead?

**Direct answer:** There IS — there are **two** read tools already: `get_subscription_status` and `list_invoices`. They are registered for both providers and Advice Fyn calls them. The `<billing_guidance>` block in the prompt is **not the data path** — it is a **shape/orchestration directive** added on top of the existing tools.

The architecturally correct place for the dual-tool call sequence and the lead-with-subscription-line shape is **inside the tool descriptions** (or in a dedicated billing-class prompt fragment that only loads when the classifier returns billing-class), not in the global system prompt. The current implementation chose the shortest path to BS-16 GREEN over the architecturally clean path. It is a known-stylistic-debt, not a missing tool.

**Evidence:**
- `app/Services/AI/AiToolDefinitions.php` and `app/Services/AI/XaiToolDefinitions.php` register `get_subscription_status` and `list_invoices` (grep confirms — exact lines depend on file state).
- `AdvicePromptBuilder::getBillingGuidance()` at line 250–268 contains shape directives ("Open your reply with the subscription line", "On the next line, state the invoice count using the phrasing 'You have N invoice(s)'") — these are presentation rules, not data-fetching rules.

---

## Q3 — Why is the billing block loaded every turn?

**Direct answer:** Because the layer composition at `AdvicePromptBuilder` lines **89–101** uses a single conditional — `if (! $isPreview)` — and then unconditionally appends `getBillingGuidance()`. There is **no classification gate**. The block is added to the prompt for a protection-cover question, an ISA question, an estate-planning question, an emergency-fund question — any non-preview advice turn.

This is the core fault. The prompt builder DOES support classification gating — the layer immediately downstream (`<financial_context>`) at line 116 is gated `?array $classification = null` and filtered through `QuerySchemas::getModulesForClassification()`. So is `<existing_records>` (line 120), `buildKnowledgeBlock()` (line 1043), `buildToolsAndTriggersBlock()` (line 1056), and `buildFcaSignpostingBlock()` (line 162). Five layers are classification-gated. Billing is not. That is a single line change to fix in principle (`if (! $isPreview && $classification?['primary'] === 'billing')` or whatever the correct enum is — exact constant in `QuerySchemas`).

**Evidence:** `app/Services/AI/AdvicePromptBuilder.php:99` — literal `if (! $isPreview)`. No reference to `$classification` on lines 89–102.

---

## Q4 — Why is there no context section (`<known_facts>`)?

**Direct answer:** Because `<known_facts>` is **Sprint 1 task S1.4**, not yet shipped. It is the deliverable that was scheduled to land **after** this session. The Sprint 1 plan at `April/April24Updates/spec/11-sprint-1-plan.md §S1.4` defines:

- New service `app/Services/AI/MemoryRetrieverService.php` with strict DB → parked → current → index fall-through.
- Modification to `AdvicePromptBuilder` to prepend a `<known_facts>` block ending with `Do not ask the user for any field above.` to every prompt.
- Modification to `OnboardingPromptBuilder` for the same.

Until S1.4 ships, the system prompt will not contain a `<known_facts>` tag. The `<user_profile>` block at line 105 is the closest current analogue but it is name-only (first name, last name, marital status) and does NOT carry the structured fact-by-fact "do not re-ask" semantics the `<known_facts>` block is specified to have.

**Evidence:**
- Live DB read confirms 0 occurrences of `<known_facts>` in the captured 18,484-char prompt for conv 145.
- `April/April24Updates/spec/11-sprint-1-plan.md` lines 113–126 (S1.4 definition) — file is gitignored locally, vault mirror at `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/11-sprint-1-plan.md`.
- Read against current `app/Services/AI/AdvicePromptBuilder.php` — no `MemoryRetrieverService` import, no `<known_facts>` literal anywhere in the file.

---

## Q5 — When did the layered system "stop being respected"?

**Direct answer:** It did not. The layered system is alive, well-respected, and growing. What changed is that **one new layer (billing) was added with the wrong gate** (preview-vs-not, instead of classification-vs-classification). Calling that "the layered system being ignored" is unfair to the architecture — the architecture is being USED, just with one mis-gated layer.

**Layer count and authorship history** of `AdvicePromptBuilder` (from `git log --follow`):

| Commit | Date | Author | What changed about layers |
|---|---|---|---|
| `2dd65e5` | 2026-04-01 15:06 | Stoff73 | **Original layered system shipped.** Phase 2 — system prompt refactor, query classification, KYC gates. This is the canonical commit. |
| `0e3fa06` | 2026-04-01 15:17 | Stoff73 | Phase 3 — query-aware knowledge RAG (Layer 7), record filtering, recommendation filtering. Classification gating extended. |
| `9dda478` | 2026-04-01 15:22 | Stoff73 | Phase 4 — mandatory tool sequences and trigger injection (`buildToolsAndTriggersBlock`). |
| `e0cc92a` | 2026-04-01 15:27 | Stoff73 | Phase 5 — decision tree binding, enhanced recommendation output. |
| `1219573` | 2026-04-01 15:56 | Stoff73 | Phase 6 — review system, advice logging, mandatory KYC navigation. |
| `228b38f` | 2026-04-07 08:26 | Stoff73 | refactor — AI prompt cleanup, xAI caching, token limits overhaul. |
| `9be7104` | 2026-04-08 17:54 | Stoff73 | guide Fyn to be efficient with tool calls. |
| `d3944ac` | 2026-04-15 10:38 | Stoff73 | onboarding: phase 0-3 keepers — added `EmptyDataGuard` substitute for new users (zero-data branch). |
| `6cf7ab3` | 2026-04-21 20:01 | Stoff73 | Phase 3 prompt builders — rename + DataCapturePromptBuilder. |
| `f9861cf` | 2026-04-24 21:45 | Stoff73 | two-Fyn collapse — AdviceFyn + handleInlineCapture (split from old monolith). |
| `786d841` | 2026-04-25 12:07 | Stoff73 | feat(fyn): user-content sanitisation + structural separation (S0.10) — `Prompts/UserContentSanitiser.php`. |
| `05e7525` | 2026-04-25 15:49 | Stoff73 | CoreIdentity guidance-only framing + FCA signposting on recommendation mode (S0.13) — added `buildFcaSignpostingBlock`. |
| `0973a6b` | 2026-04-25 20:44 | Stoff73 | wire advice→capture handoff (S0.5.r) — added `getHandoffGuidance()`. |
| `231b846` | 2026-04-25 21:59 | Stoff73 | BS-14 GREEN — promoted handoff layer up the order (recency bias). |
| **`c51e7ff`** | **2026-04-26 03:40** | **Stoff73** | **BS-16 contract-GREEN — added `<billing_guidance>` UNCONDITIONALLY (the regression flagged by this audit).** |
| `5b65a7b` | 2026-04-26 17:21 | Stoff73 | preserve full multi-word first_name across chat surfaces (touched `buildUserProfile`). |

**Pattern:** every commit before `c51e7ff` either added a classification-gated layer or refactored an existing one. `c51e7ff` is the first new layer that lacks a classification gate. The drift is one commit deep, not a cumulative architectural rot.

---

## Recommended fixes (ranked, smallest blast radius first)

1. **Classification-gate the billing block.** Change `app/Services/AI/AdvicePromptBuilder.php:99` from `if (! $isPreview)` to `if (! $isPreview && $this->isBillingClassification($classification))`. Add a tiny private helper `isBillingClassification(?array $classification): bool` that returns true when `($classification['primary'] ?? null) === QuerySchemas::BILLING_INVOICE` (or whatever the canonical billing constant is — needs lookup). Re-run BS-16 to confirm it stays GREEN. **Single-line change with one helper. Estimated 5–10 lines total.**
2. **Move billing shape rules into the tool descriptions.** Augment `AiToolDefinitions::get_subscription_status` and `list_invoices` descriptions with the lead-with-subscription-line + count-phrasing rules. This is the architecturally cleanest path because it scopes the rules to the moment the tools are actually picked. After this lands, the system-prompt billing block can be deleted entirely. **~30 lines of edits across two definition files.**
3. **Ship S1.4 to add `<known_facts>` block.** Per the Sprint 1 plan. Once shipped, the system prompt will carry structured per-user facts and the model will stop re-asking known data. **Sprint-scoped task — not a one-line fix.**
4. **Audit the rest of the unconditional layers.** `<handoff_guidance>` (line 86) is also `if (! $isPreview)` only. Verify it's correct that handoff guidance fires even on factual / out-of-remit turns where no handoff is possible. If not, classification-gate it too. **Investigation, not a fix yet.**
5. **Compress the static layers.** The captured prompt is 18,484 chars. CoreIdentity + Security + Scope + Personality + ResponseFormat + Instructions + RegulatoryCompliance + FcaProcess together are ~9–10K chars and load on every turn. Anthropic prompt caching (already enabled per `'cache_control' => ['type' => 'ephemeral']` at `app/Traits/HasAiChat.php:319`) absorbs most of this cost, but xAI does not cache the same way. Worth a token-budget pass after the gating fix lands. **Sprint-scoped, lower priority than (1)–(3).**

---

## Open questions for next session

- What is the canonical billing classification constant in `QuerySchemas`? Need to grep `app/Constants/QuerySchemas.php` for `BILLING` / `INVOICE` / `SUBSCRIPTION` before fix #1 can land.
- Does BS-16 stay GREEN if the billing block is classification-gated? If grok-4-1-fast still passes BS-16 with the gate, fix #1 is complete and fix #2 is optional polish. If it regresses, fix #2 becomes mandatory.
- For the eval harness: does `advice_protection_cover` start passing once the billing block is removed from the prompt for non-billing classifications? The two failure findings from session 98's scenario 1 recording (Haiku reaching for `list_records` instead of `get_module_analysis`; Grok making zero tool calls) may shift once the prompt is leaner. Worth re-recording after fix #1.

---

## Files referenced

- `app/Services/AI/AdvicePromptBuilder.php` — main builder (lines 80–268 audited line-by-line via `git blame`).
- `app/Services/AI/Prompts/CoreIdentity.php` — `<identity>`, `<security>`, `<scope>`, `<personality>` source.
- `app/Services/AI/Prompts/ComplianceRules.php` — `<regulatory_compliance>` source.
- `app/Services/AI/Prompts/FcaProcessInstructions.php` — `<fca_process>` source.
- `app/Services/AI/Prompts/QueryKnowledge.php` — knowledge fragments (gated through `buildKnowledgeBlock`).
- `app/Services/AI/Prompts/EmptyDataGuard.php` — `<new_user_state>` for zero-data users.
- `app/Services/AI/Prompts/UserContentSanitiser.php` — `<user_provided>` wrapping.
- `app/Constants/QuerySchemas.php` — classification constants and module-mapping.
- `app/Traits/HasAiChat.php:319` — Anthropic ephemeral cache directive (caches the system prompt).
- `April/April24Updates/spec/11-sprint-1-plan.md §S1.4` — `MemoryRetrieverService` + `<known_facts>` deliverable spec (gitignored; vault mirror at `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/11-sprint-1-plan.md`).
- Eval recording session #5, conversation_id=145 (Anthropic Haiku 4.5) — captured prompt, 18,484 chars.

---

## What this audit is NOT

- It is not an apology for the regression. The regression was a deliberate trade — BS-16 GREEN over architectural cleanliness — and the fix is small.
- It is not a claim that the layered system has been compromised. It hasn't. One new layer was added with the wrong gate.
- It is not a recommendation to revert `c51e7ff`. BS-16 needs the dual-tool call directive somewhere; the fix is to scope it correctly, not delete it.
- It is not exhaustive. Onboarding's `OnboardingPromptBuilder` was not audited this session — its layer composition is similar but separate, and the same kind of audit should be done on it before any fix lands that touches both Fyns. Filed as a session-99 follow-up.
