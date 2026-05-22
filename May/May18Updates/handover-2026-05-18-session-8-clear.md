---
type: handover
mode: context-clear
date: 2026-05-18
session: 8
branch: fynPromptRework
trigger: context-handover skill (context tripwire ~287k / 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 8

## Immediate state

Done and folded into PR #335. Restored verbatim system-prompt capture
(F-8 regression revert) — **browser-verified GREEN** in the admin AI
Audit view — plus a fail-loud cassette-provenance guard test and the
stale grok model-name rename. All committed as `1374d2b` on
`fynPromptRework`, pushed → PR #335. Tripwire fired immediately after
push; nothing left in-flight.

## The thread

- Auto-resumed handover-7. Did the WIP-`20ac500` fold context (left as
  discrete commit on #335 — acceptable per handover-7) and ran the
  stale-`grok-4-1-fast` scan. Triaged into A/B/C/D/E categories.
- CSJ chose **A + C + D**: fixed CoordinatingAgent:2780 comment (A);
  bulk-renamed 34 `model_used` test-fixture strings across 4 test files
  (C); BS-11:85 / BS-17:60 docblock prose + MockedProviderClient:18
  comment (D). Flagged the eval-cassette **directory** (`fixtures/xai/
  grok-4-1-fast-reasoning/`) as NOT category C — it's a provenance
  contract, re-record territory, not a cosmetic rename. Did NOT touch it.
- Misread CSJ's "system prompt not captured in the view" as new design
  work → wrongly invoked brainstorming + asked a hash-vs-verbatim
  question. **CSJ corrected hard**: this is an EXISTING admin AI-eval
  view (expandable full-prompt link) that worked for grok-4.1 — "why
  are you trying to redo this?". Dropped brainstorming, switched to
  root-cause investigation.
- Root cause found: April30 **F-8** (merged `0335ffd`, May 5) replaced
  `ai_messages.system_prompt` with `'sha256:'.hash(...)` at
  `HasAiChat.php:762`. Single writer; two readers
  (`EvalRecordingController:186` → eval RunPanel, `AiAuditController:112`
  → AiAudit.vue) pass it straight through. Post-F-8 rows show a hash
  instead of the prompt. One-line revert to verbatim `$systemPrompt`
  (same value sent to provider, incl. per-turn FynContextAssembler
  layers). `longtext` column → no truncation.
- Browser-verified GREEN end-to-end (Rule #15): john Fyn chat turn →
  msg id=65 conv=22 len=14,734 not sha256 → logged in as chris (admin)
  → Admin → AI → AI Audit → John Smith → "What is my net worth?" →
  "Show System Prompt (3684 tokens)" expands to the **full readable
  grok-4.3 prompt** (`<identity>…<fca_signposting>`), not a hash.
- Issue #2 (orphaned xai cassettes): CSJ chose **"add a guard test
  only"**, defer re-record. Wrote `CassetteModelProvenanceTest.php`
  (fail-loud, intentionally RED until re-record, auto-greens after).
  Verified RED-as-designed with actionable message + companion test
  PASS (pins `MockedProviderClient::defaultFixturePath` contract).
- CSJ: "fold into 335" + this handover instruction. Committed `1374d2b`,
  pushed to `fynPromptRework` (PR #335 now 7+ commits, still OPEN, NOT
  self-approved/merged).

## Files touched this session

```
Commit 1374d2b (10 files, +143 / -53):
  app/Traits/HasAiChat.php                         F-8 revert (:762 verbatim)
  tests/Feature/Fyn/Eval/CassetteModelProvenanceTest.php   NEW fail-loud guard
  app/Agents/CoordinatingAgent.php                 :2780 grok-4.3 comment (A)
  tests/Browser/scenarios/BS-11-handoff-invisibility.php   :85 docblock (D)
  tests/Browser/scenarios/BS-17-multi-entity-persist.php   :60 docblock (D)
  tests/Feature/Fyn/Eval/MockedProviderClient.php  :18 comment (D)
  tests/Feature/AI/ConversationIndexPopulationTest.php     model_used (C)
  tests/Feature/AI/SearchConversationIndexTest.php         model_used (C)
  tests/Unit/Services/AI/MemoryRetrieverServiceTest.php    model_used (C)
  tests/Unit/Services/Onboarding/KnownFactsBlockTest.php   model_used (C)
```

## WIP commit

- SHA: `1374d2b` — **clean feature commit** (NOT a generic wip snapshot;
  CSJ asked for "fold into 335"). Message documents F-8 revert + guard +
  rename. Do NOT re-squash standalone — it folds into PR #335's eventual
  squash-merge to `dev`.
- Pushed: **yes** (`origin/fynPromptRework`, `eaf4979..1374d2b`).

## Open decisions

- **None blocking.** Both issues handled per CSJ's explicit choices
  (A+C+D rename; verbatim restore; guard-test-only for cassettes).
- PR #335 awaits CSJ review/merge — do NOT self-approve
  (`feedback_no_self_approval`, `feedback_main_via_dev_only`).
- Cassette re-record (`php artisan eval:record --providers=xai`, 11
  scenarios, live xAI spend) deliberately deferred to a separate
  session. Default direction: separate workstream, not folded into #335.

## Pick up from here (auto-continue contract)

PR #335 deliverable is complete; no in-flight implementation. Next
session, in priority order:

1. **Eval-system enhancement (CSJ-instructed, NOT yet started).** The
   admin AI-eval view now shows the verbatim system prompt again — but
   CSJ wants it to ALSO surface, reflecting the new **unified** prompt
   approach:
   - **Tool calls** made during the turn (the SSE `tool_use` events /
     `ai_messages.metadata.tool_calls` — already captured for eval
     recordings via `EvalRecordCommand::enrichToolCallsWithResults`,
     but the live AiAudit message view should show them too).
   - **Context collected** — the per-turn `FynContextAssembler` layers
     assembled under unified (financial_context, existing_records, KYC,
     classification buckets, etc.). Under unified the effective prompt =
     static `FynSystemPrompt::text()` + assembler layers; the view
     should make the assembled context visible alongside the static
     prompt so an admin can see exactly what the model received this
     turn, not just the static base.
   - Scope: `AiAuditController` + `AiAudit.vue` (and the eval RunPanel
     equivalent `EvalRecordingController`/`RunPanel.vue`). Likely needs
     persisting the assembled context (or reconstructing it) the same
     way `system_prompt` is persisted on the assistant `ai_message`.
   - This is a feature → start with `superpowers:brainstorming` (a real
     design question this time — where to capture the assembled context,
     whether to persist it on the message row, how the view renders
     tool calls + context without bloat). Do NOT just hack the view.
2. **Cassette re-record (separate, deferred).** `php artisan eval:record
   --providers=xai` for the 11 scenarios under
   `tests/Feature/Fyn/Eval/scenarios/` (live xAI spend, non-deterministic),
   then remove stale `fixtures/xai/grok-4-1-fast-reasoning/`. This
   greens `CassetteModelProvenanceTest`. Its RED is the intended signal —
   do NOT silence the test; fix by re-recording.
3. **Await CSJ on PR #335** — review/merge only when CSJ says.

## What the next Claude needs to know

- **`CassetteModelProvenanceTest` is intentionally RED.** Full
  `./vendor/bin/pest` now has 1 known-red test until xai cassettes are
  re-recorded under grok-4.3. This is CSJ's explicit "never silent
  again" contract + `feedback_evals_surface_engineering_issues`. The
  fix is re-recording (item 2), NOT skipping/`->todo()`/deleting the
  assertion. The failure message names the exact remediation.
- **`HasAiChat.php:762` now stores verbatim** the effective system
  prompt sent to the provider (static base + per-turn
  FynContextAssembler layers under unified). `ai_messages.system_prompt`
  is `longtext`. F-8's PII/bloat rationale was explicitly overridden by
  CSJ — admin-only forensic data, never user-exposed.
- **The eval view enhancement is the next real task.** "Show System
  Prompt" works; CSJ wants tool-calls + assembled-context surfaced too,
  reflecting unified. Tool-call data already lands in
  `ai_messages.metadata.tool_calls`; assembled context is NOT currently
  persisted per-message (only the final prompt string is) — that's the
  design crux for brainstorming.
- Live model is `grok-4.3` (`config/services.php:42-44`). grok-4-1-fast
  is retired; remaining references are historical/dated docs (category
  E — leave) and the orphaned cassette dir (item 2).
- Two-Fyn unified contract unchanged. PR #335 still OPEN, flag default
  `unified`. Don't pass `FYN_PROMPT_ARCH`.
- Worktree `.claude/worktrees/tender-bassi-375ee8` on `freemium`
  (5a5478b, clean) — sub-project 2, leave intact.
- Brainstorming-skill misfire lesson: when CSJ says "why are you redoing
  this?" about an existing surface, it's a regression hunt
  (systematic-debugging), not new design. Find the existing mechanism
  first.
- Memory relevant: `reference_unified_prompt_has_no_billing_layer`,
  `feedback_evals_surface_engineering_issues`,
  `feedback_advice_fyn_is_read_only`, `feedback_no_self_approval`,
  `critical_browser_testing_law`, `feedback_loop_until_correct`.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (all pushed incl. `1374d2b` + this handover commit)
- PR: **#335 OPEN** → `dev`, awaiting CSJ review (NOT self-approved)
- Deploy status: Not deployed (feature branch)
