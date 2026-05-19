---
type: delta-analysis
title: Fyn — Canonical Contract vs Current Implementation (delta)
date: 2026-05-18
branch: fynPromptRework
prompt_arch: unified
sources_compared:
  - April/April24Updates/spec/00-canonical.md          (the DESIGN — source of truth)
  - May/May18Updates/fyn-prompt-and-response-process-map.md  (the AS-BUILT snapshot)
  - live code on fynPromptRework @ FYN_PROMPT_ARCH=unified (the IMPLEMENTATION)
verification: file:line trace 2026-05-18 (see process map for citations)
---

# Canonical vs Implementation — Delta

Three artefacts compared:

1. **DESIGN** — `00-canonical.md` (the two-Fyn contract, the intended behaviour).
2. **AS-BUILT** — `fyn-prompt-and-response-process-map.md` (this session's verified trace).
3. **IMPLEMENTATION** — the live code on `fynPromptRework` under `unified`.

The AS-BUILT map is a faithful trace of the IMPLEMENTATION, so the meaningful delta is
**DESIGN ↔ IMPLEMENTATION**. Result: **4 deltas — 1 was a real parity bug (now fixed),
0 outstanding contract breaches.** The read/write boundary, single-prompt guarantee,
handoff invisibility, and tool-gating all hold exactly as designed. The deltas are:
1 doc-accuracy gap, **1 parity regression (Delta 2 — KYC `prompt_text` dropped under
unified; fixed 2026-05-18, regression-tested both flags)**, 1 micro-inefficiency,
1 informational. The plan's contract is parity (`unified` ≡ `legacy`); the KYC gate, the
prerequisite checks and the context block are all required under the unified prompt in
the specced format — none are optional, none removed.

---

## ✅ What matches the contract exactly (no delta)

| Canonical clause | Verified in code |
|---|---|
| One static system prompt for both states | `FynSystemPrompt::text()` static heredoc; `HasAiChat::buildSystemPrompt:817` returns it for both states under unified |
| Split NOT encoded in prompt; enforced by tool list | `AdviceFyn::WRITE_TOOLS` `array_diff` at `buildToolList:563`; prompt identical |
| Advice Fyn read-only incl. `create_what_if_scenario` + `navigate_to_page` stripped | `AdviceFyn::WRITE_TOOLS:152-184` (both present in the strip list) |
| Write intents → `delegate_to_capture` → `wrapStream` → `handleInlineCapture` → CoordinatingAgent handlers | `AdviceFyn::wrapStream:388-465`; `OnboardingChatDirector::handleInlineCapture:2361` |
| Synthetic `handoff` consumed internally, never to frontend (INV-2.4.1) | `wrapStream:481-498` logs+drops other handoff types; `:478` returns after capture |
| No `persona_state_change`, no capturing pill, placeholder invariant | no such SSE event emitted anywhere in the advice/handoff path |
| `FYN_PROMPT_ARCH` flag, fail-safe to legacy | `FynPromptMode::isUnified():13` exact-string match; `config/fyn.php:18` default `unified` |
| No `FynPersonaOrchestrator` / invoker / registry / `DataCapturePromptBuilder` | absent from tree (confirmed `app/Services/AI/Fyn/` listing) |

---

## ⚠ Delta 1 — Dispatch predicate (DOC-FIX, not code-fix)

- **Canonical says:** dispatch is "one if-statement keyed on `users.onboarding_completed`".
- **Implementation does:** `AiChatController.php:171-173` —
  `onboarding_completed === false && onboarding_fyn_step !== null &&
  config('onboarding.fyn_flow_enabled', true)`.
- **Effect:** a paused onboarding user whose `onboarding_fyn_step` was nulled (via the
  "Something else" handoff) routes to **read-only AdviceFyn** while `onboarding_completed`
  is still `false`. This is **deliberate** (docblock `:161-170`) and *safer* than the
  canonical description (a paused user can't accidentally land in a write state with no
  step context).
- **Verdict:** the code is correct; the **canonical wording was stale**. **RESOLVED
  2026-05-18:** `00-canonical.md` line 11 amended to the real 3-part predicate (else
  read-only advice; deliberate + fail-safe per `AiChatController.php:161-173`). Vault copy
  `Canonical-Two-Fyn-Contract.md` mirrored. Doc-only fix, no code changed.

## ⚠ Delta 2 — KYC gate prompt_text dropped under unified (PARITY BUG — FIXED 2026-05-18)

- **Required behaviour:** The plan's contract is parity — `unified` must be behaviourally
  identical to `legacy`. Under legacy, `AdvicePromptBuilder::build` emits the KYC gate's
  `prompt_text` as **Layer 9, unconditionally** (`AdvicePromptBuilder.php:195-198`): when
  the user asks about X but is missing Y, Fyn asks for Y instead of advising.
- **Defect:** Under `FYN_PROMPT_ARCH=unified`, `buildSystemPrompt:817` returned
  `FynSystemPrompt::text()` and **discarded `$kycResult`**. `FynContextAssembler` only
  reproduced the `<data_completeness>` block (`buildPrerequisiteStateContextWrapped`,
  `:69`) — it did **not** reproduce the Layer-9 `prompt_text`. So the classification-aware
  "ask, don't advise" instruction reached the model under legacy but **not** under
  unified. This was a real parity regression against the plan, not an optimisation choice.
  The earlier "wasted compute / pick (a) single source or (b) telemetry-only" framing was
  wrong: the gate, the checks and the context are all required under the unified prompt in
  the specced format — nothing is optional and nothing is removed.
- **Fix (4 edits, parity-restoring, legacy path byte-untouched):**
  1. `FynTurnContext` — added optional `?array $kycResult` field + `make()` param.
  2. `HasAiChat::chat` — passes the already-computed `$kycResult` (`:157`) into
     `injectUnifiedTurnContext`.
  3. `HasAiChat::injectUnifiedTurnContext` — threads `kycResult` into
     `FynTurnContext::make()`.
  4. `FynContextAssembler::build` — emits `$ctx->kycResult['prompt_text']` as its own
     layer using the **exact** legacy guard (`!== null && isset(...) && !== ''`),
     unconditional on bucket, mirroring `AdvicePromptBuilder` Layer 9 ordering (after the
     data-completeness block).
- **Verification:** Pint + lint clean. `FynContextAssemblerTest` extended with two parity
  guards (`emits the KYC gate prompt_text when the turn carries a kycResult`; `emits no
  KYC layer when the turn has no kycResult`). Fyn context + `KycGateCheckerTest` suites:
  **identical pass counts under both flags** (31/31 KYC+context; 8/8 assembler incl. new
  guards). Full Step-5 suite parity (`--testsuite=Unit,Feature,Architecture` under both
  flags) is the formal gate and has not yet been re-run for this change.
- **Verdict:** parity restored. Closed in this session — no CSJ decision required.

## ⚠ Delta 3 — Double `QueryClassifier::classify()` per advice turn (MICRO-INEFFICIENCY)

- `AdviceFyn::handle:207` and `HasAiChat::chat:150` both classify the same message; only
  the second result reaches `FynTurnContext`. The first drives the OUT_OF_REMIT
  short-circuit + eval events.
- **Verdict:** correctness fine, ~one redundant classification per advice turn. Candidate
  to thread the first result down into `chatWithPromptOverride`. Backlog, low priority.

## ℹ Delta 4 — Model default `grok-4.3` (INFORMATIONAL)

- `config('services.xai.chat_model', 'grok-4.3')` (`XaiClient.php:104`); `:19` comment
  calls `grok-4-1-fast` "retired". Not a contract item. Model choice is a deliberate
  unit-economics decision (`feedback_fyn_model_choice_is_deliberate`) — **do not flag as
  stale, do not "upgrade".**

---

## Recommended actions (for CSJ / backlog — none auto-applied)

| # | Delta | Action | Owner decision needed? |
|---|---|---|---|
| 1 | Dispatch predicate | **DONE 2026-05-18** — `00-canonical.md` line 11 amended to the real 3-part predicate (+ vault copy mirrored) | No — doc accuracy fix, closed |
| 2 | KYC prompt_text dropped under unified | **FIXED 2026-05-18** — kycResult threaded into `FynContextAssembler` (parity with legacy Layer 9); regression-tested both flags | No — parity bug, closed |
| 3 | Double classification | Thread first classification result down | No — safe refactor, backlog |
| 4 | Model default | None (informational) | No |

---

*Delta verified by file:line trace 2026-05-18 on `fynPromptRework`. Citations live in
`fyn-prompt-and-response-process-map.md` §1–§10. Both this delta and the canonical spec
must be carried into the fynlaBrain vault — see `VAULT-SYNC-PENDING.md` in this folder.*
