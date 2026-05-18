---
type: investigation
date: 2026-05-18
session: 5 (auto-resume from session-4-clear)
branch: fynPromptRework
subject: Journey-B tripled capture-ack — classification + root cause
status: RESOLVED (browser-GREEN single + multi-entity); parity gate re-running post-fix
---

# Tripled capture-ack — classification & root cause

Auto-resumed from `handover-2026-05-18-session-4-clear.md`. Pickup contract:
step 1 = parity-clean gate; step 2 = classify the tripled ack
unified-specific vs pre-existing-on-legacy.

## Step 1 — parity-clean gate: GREEN (resolved)

Background job `b12uga51v` (`/tmp/fyn-parity-clean.log`), dev server stopped:

```
--- LEGACY ---   Tests: 1 skipped, 3728 passed (14869 assertions)  379.73s
--- UNIFIED ---  Tests: 1 skipped, 3728 passed (15575 assertions)  356.88s
```

Identical pass/skip both flags = GREEN per the handover's definition.

## Step 2 — classification: tripled-ack is UNIFIED-SPECIFIC

Same message, same deterministic `WriteIntentClassifier` route
(user[advice] → assistant[data_capture]), browser-reproduced live (john
user_id=11, `onboarding_completed=false`):

| Flag | Provider | Result | Row | Ack |
|------|----------|--------|-----|-----|
| unified | Starling £2,750 | captures | ✅ 1 (id 308) | **"Got it — recording that now." ×3** (persisted, ai_messages id 37) |
| legacy | Halifax £3,250 | **security refusal** | ❌ 0 | "I can only help with financial planning questions." (single) |
| legacy | Lloyds £4,100 | **security refusal** (re-run, consistent) | ❌ 0 | same |

Unified SSE (req #236) — model ran create_savings_account ×3 across the
`HasAiChat` while-loop; only 1 persisted (dedup); preamble streamed on every
iteration. Legacy SSE (req #226) — single security-clause refusal, no tool
call (input_tokens 20243 vs unified 63251).

**Verdict:** tripled-ack is unified-specific. Legacy does NOT triple — it
refuses the whole journey. Per the handover's decision rule
("only unified → parity regression, fix per Rule #15") it IS a PR blocker.
BUT the handover's prescribed remedy ("mirror legacy's stop-after-create
signal") is **invalidated by evidence**: legacy has no such signal — legacy
never creates here.

## Root cause (unified tripled-ack)

`handleInlineCapture` (and identically `handleAssetCaptureTurn`, the normal
onboarding bubble capture) → `chatWithPromptOverride(persona:data_capture)`
→ `HasAiChat::chat` `while(true)` loop. The loop continues whenever
`stop_reason === 'tool_use'` (`HasAiChat.php:685`). The unified CAPTURE
prompt `FynCaptureTurnInstructions` says *"YOUR SINGLE JOB: call create_ for
EACH record"* and *"single short confirmation sentence"* but has **no
instruction to STOP after a successful create / not re-narrate / not
re-call create on the next loop iteration**. The create success result
(`CoordinatingAgent.php:2099` `"I've added your X"`) and the duplicate
`warning` result (`checkForDuplicate` :3425) carry no "turn complete —
end now" signal. So the re-invoked model re-emits the preamble + create
each iteration until it happens to stop (iteration 3). All preambles stream
+ concatenate into the persisted assistant message.

`FynCaptureTurnInstructions` docblock: *"lifted VERBATIM from
OnboardingPromptBuilder Layer 3 (D4 — preserve wording)"* — spec-governed
parity artifact, shared by the (currently-passing) onboarding bubble flow.

## Separate, SEVERE, pre-existing finding — legacy rollback broken for this journey

Under `FYN_PROMPT_ARCH=legacy` the advice→capture write journey
(`WriteIntentClassifier` → `handleInlineCapture` → data_capture turn)
**security-refuses, zero create, zero row** — reproduced 2× consecutively.

- Branch diff `origin/dev...HEAD` = 4 files, all unified-path
  (`FynContextAssembler` +11, `FynTurnContext` +3,
  `OnboardingChatDirector` +92/-33, `HasAiChat` +3). The
  `OnboardingChatDirector` change is explicitly `FynPromptMode::isUnified()`
  gated and documented **"no-op under legacy"**. Legacy prompt builders
  untouched.
- ∴ legacy-refusal is **pre-existing on origin/dev**, NOT introduced by this
  branch. Per the handover contract it is **NOT this branch's PR blocker**
  (log as separate issue).
- BUT it means the documented **emergency-rollback path (legacy) cannot
  perform the advice→capture write journey**. The deterministic Pest parity
  gate (3728/1 both flags) cannot catch it — the LLM is mocked; the refusal
  is live-grok behaviour driven by legacy prompt content
  (cf. memory `feedback_eval_must_drive_full_user_journey` — prompt parity
  ≠ flow parity).

## Fix options for the unified tripled-ack (CSJ decision — Rule #15 exit-b)

The handover's "mirror legacy" remedy is undefined (legacy refuses). The
correct fix surface is spec-governed (D4 verbatim) and shared with the
passing onboarding flow. Options:

- **A. Tool-result terminal signal (recommended, lowest risk, no prompt
  divergence).** In the create_*/update_* success + duplicate-warning
  results returned to the model in capture turns, add an explicit
  `"turn_complete": true` + terse instruction ("Record saved. Do not call
  create again. Reply with one short confirmation, then stop."). Data-layer
  nudge only; does NOT touch the D4-verbatim `FynCaptureTurnInstructions`
  wording; applies to both unified capture entry points identically.
  Risk: model may still ignore; needs browser re-verify both entry points.
- **B. Loop guard in `HasAiChat::chat`.** In data_capture persona, once a
  `create_*`/`update_*` has produced `entity_created` (or a dedup warning)
  this turn, stop continuing the loop on the next `tool_use` (force one
  final tools-disabled text pass, like the `toolCallCap` branch at :691).
  Targeted, deterministic. Risk: flag-shared + multi-entity capture
  ("Halifax ISA £10k and Nationwide saver £5k") legitimately needs N create
  calls in ONE turn — guard must count distinct entities, not first-create.
- **C. Amend `FynCaptureTurnInstructions` (D4 wording).** Add a "STOP after
  you have called create_ for every record — do not repeat your
  acknowledgement, do not call create again" clause. Architecturally
  cleanest but **breaks the D4 "preserve VERBATIM from legacy Layer 3"
  spec constraint** and changes the prompt for the whole passing onboarding
  bubble flow — needs full onboarding re-verification + spec amendment.

Recommendation: **A**, with the multi-entity case explicitly preserved
(only signal turn_complete once the user's described records are all
persisted, not after the first). Re-verify in browser under unified for
BOTH single-entity (advice→capture) and multi-entity (onboarding bubble)
before the dev PR.

## Resolution (CSJ chose Fix A + log-legacy-separately)

Two coupled root causes found while looping (Rule #15 — fixed in-loop):

1. **Invalid `account_type`.** The model consistently emits
   `account_type:"savings_account"` (the user said "a savings account"
   with no type). `CoordinatingAgent::handleCreateSavingsAccount`
   hard-rejected it (`Rule::in` enum) → `validation_failed` → no row on
   the FIRST call. The model only *sometimes* self-corrected on a retry
   (Starling did; Lloyds did not → intermittently zero rows). This was the
   true root of "no row" AND the extra narrate-loop iteration.
   **Fix:** coerce a recognised synonym / unknown `account_type` to a
   sensible default BEFORE validation (`easy_access`, or `cash_isa` when
   `is_isa`). `CoordinatingAgent.php` +37.
2. **No turn-complete signal (Fix A).** The data_capture `HasAiChat`
   while-loop re-invokes the model with the create tool_result still in
   context; `FynCaptureTurnInstructions` (D4-verbatim, untouched) has no
   "you are done" signal. **Fix:** `captureTurnCompleteDirective()` —
   attaches a terse terminal instruction to the tool_result the model
   reads, gated on `persona==='data_capture'` and **only when a write
   actually LANDED or was DEDUPED** (keyed on result shape, never the tool
   NAME — the early bug that fired on a failed create and suppressed the
   retry). `HasAiChat.php` +75. Multi-entity safe: all N creates fire as
   parallel tool_use blocks in the model's first response; the directive
   only forbids RE-calling on the next continuation.

Browser re-verification under live `unified` (john user_id=11):

| Case | Message | Rows | Ack | SSE |
|------|---------|------|-----|-----|
| single | "Add a savings account: Lloyds, balance 4100 pounds" | 1 — id 309 Lloyds Savings easy_access £4,100 (account_type coerced from "savings_account") | "Got it — recording that now." + "Saved your Lloyds savings account." (one ack + one distinct confirmation, NO verbatim ×3) | 1 create → entity_created 309 → 1 confirmation → capture_complete |
| multi | "Add two savings accounts: Tesco Bank with 6000 pounds and Santander with 7500 pounds" | 2 — id 310 Tesco Bank £6,000, id 311 Santander £7,500 (both coerced) | "Got it — recording those now." + "Recorded both savings accounts." | 2 parallel creates → entity_created 310+311 → 1 combined confirmation → capture_complete (2 records) |

Verbatim ×3 repetition eliminated in both cases; multi-entity onboarding
path NOT regressed. Pint passes on both changed files.

Post-fix parity gate: running clean (`/tmp/fyn-parity-postfix.log`, bg
job — both flags Unit,Feature,Architecture). Must be GREEN (identical
pass/skip both flags; baseline legacy 3728/1) before the dev PR per the
handover step-3 gate. The HasAiChat directive is `data_capture`-gated so
inert on advice + legacy-refusal paths; the account_type coercion is
flag-agnostic and pure-additive (only rewrites an already-invalid value).

## Live state at handover-out

- Branch `fynPromptRework`, clean, WIP `a035ab6` pushed (unchanged this session).
- Parity gate GREEN (above) — step 1 done, durable in this doc.
- Dev server: artisan serve `FYN_PROMPT_ARCH=legacy` pid 36707 on :8000;
  fynla vite pid 38575 on :5173; `public/hot` = `http://127.0.0.1:5173`.
  (Was started under legacy for step-2 classification; restart WITHOUT the
  env var — or via `./dev.sh` — to return to unified default before fixing.)
- DB: john user_id=11 — Starling id 308 £2,750 persisted (unified test),
  Halifax/Lloyds NOT created (legacy refusals). 4 savings accounts total.
- `public/hot` had gone MISSING mid-session (caused a transient
  `/fynla/` base-path redirect — stale csjones build served); fixed by
  clean vite restart (memory `feedback_public_hot_stale_chunks`).
