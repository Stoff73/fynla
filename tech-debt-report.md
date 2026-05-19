# Tech Debt Report — Session 2026-05-18 (PR #335 full delta)

**Scope:** `git diff origin/dev...HEAD` on `fynPromptRework` — committed PR #335 delta (tree clean, no uncommitted work). Carried-over audit, interrupted sessions 10 and 11.
**Files analysed:** 14 code files (app/ PHP, config, migration, resources/js). 23 docs/handover markdown files in the diff were excluded — not code debt.
**Issues found:** 7 (1 critical [pre-existing in PR #335], 2 warnings, 4 suggestions)
**Severity breakdown:** 1 critical, 2 warnings, 4 suggestions
**Status:** W2 RESOLVED this session (billing parity fully restored + browser-verified). W1 DEFERRED by CSJ decision (noted for future purge). C1 newly surfaced — pre-existing RED test shipped by PR #335 itself.

## Critical Issues

### C1 — PR #335 ships a RED test: `CassetteModelProvenanceTest` (stale xai cassette directory)
- **File:** `tests/Feature/Fyn/Eval/CassetteModelProvenanceTest.php:77` (new file added by PR #335)
- **Category:** 2 (Dead/stale fixtures) / 6 (CI integrity)
- **What's wrong:** PR #335 migrated the model default `grok-4-1-fast` → `grok-4.3` AND added a new provenance test asserting no orphaned cassette directories. 11 cassettes remain stranded under `tests/.../xai/grok-4-1-fast-reasoning/` while `config('services.xai.chat_model')` is now `grok-4.3`, so the new test is RED on the branch. Verified pre-existing and orthogonal to this session's billing work (fails identically with or without the working changes; the test only compares cassette dir names to the config model). The concern: PR #335 currently merges a failing test into `dev`.
- **Suggested fix:** `php artisan eval:record --providers=xai` then delete the stale `xai/grok-4-1-fast-reasoning/` directory, committed onto PR #335 before merge. Out of this session's billing scope — reported, not actioned (scope discipline).

## Resolved / deferred this session

- **W2 (billing) — RESOLVED.** `<billing_guidance>` restored as a classification-gated `FynContextAssembler` per-turn layer (parity with legacy Layer 3c); `AdvicePromptBuilder::isBillingQuery()`/`getBillingGuidance()` promoted to public for verbatim reuse; `QueryClassifier` precedence fixed so billing entities beat NAVIGATION; `QuerySchemas` BILLING broadened (bare `billing`/`subscription`, ISA-subscription guarded). +3 assembler + +7 classifier Pest cases, all green. Browser-verified GREEN under unified (DB: classified `billing`, `<billing_guidance>` in assembled_context, both billing tools fired, pinned response shape). See memory `feedback_fyn_reaches_every_surface` + `reference_unified_prompt_has_no_billing_layer`.
- **W1 (PII/bloat) — DEFERRED by CSJ.** Leave the forensic capture as-is; noted for a future DB-hygiene/retention sweep. Memory `project_ai_messages_forensic_columns_need_purge`.

## (original) Critical Issues

None at audit time.

## Warnings

### W1 — F-8 reversal reintroduces PII duplication + DB bloat in `ai_messages`, with no retention policy
- **File:** `app/Traits/HasAiChat.php:772-538` (the `$assistantExtra` persistence block)
- **Category:** 5 (Security — sensitive data at rest) / 4 (Maintainability — DB bloat)
- **What's wrong:** This commit deliberately reverts April30 F-8. `system_prompt` now persists the **verbatim ~10KB prompt** (embeds income, family names, full financial position) on **every assistant message row**, not a `sha256:` hash. On top of that it adds three new uncompressed columns per assistant row: `assembled_context` (longText, full per-turn PII context), `tool_calls`, and `tool_results` (explicitly "No cap — admin-only forensic", storing the raw uncompressed tool output **and** the verbatim sent-to-LLM payload). Across a long conversation this is multi-KB × PII × every assistant row — exactly the duplication F-8 existed to eliminate. The inline comment acknowledges the tradeoff ("forensic requirement overrides the bloat concern"), which is a legitimate product call. The gap the audit flags: there is **no retention/purge path** for these columns. `php artisan audit:purge` (CLAUDE.md) targets audit logs, not `ai_messages` rows. PII now accumulates in plaintext indefinitely.
- **Suggested fix:** Not a code change to make now (the tradeoff is intentional and CSJ-scoped). Track as a follow-up: add an `ai_messages` forensic-column retention sweep (e.g. null out `system_prompt`/`assembled_context`/`tool_results` older than N days) to an existing scheduled command, or extend `audit:purge`. Decision belongs to CSJ — surface, don't action.

### W2 — `<billing_guidance>` removed from unified prompt with no replacement; billing journey unguided under the new default
- **File:** `app/Services/AI/Fyn/FynSystemPrompt.php:233-248` (block deleted)
- **Category:** 6 (Inconsistency with existing patterns) / 4 (behavioural regression risk)
- **What's wrong:** The entire `<billing_guidance>` block (the BOTH-`get_subscription_status`-AND-`list_invoices`, "You have N invoice(s)" phrasing contract) is deleted from the static prompt. `FynContextAssembler` adds **no** per-turn billing layer to compensate. With `config/fyn.php` now defaulting `prompt_architecture` to `unified`, every production turn loses the billing-journey scaffolding that legacy still gates on the `BILLING` bucket. This is **already documented** in memory `reference_unified_prompt_has_no_billing_layer.md` ("Re-add as a per-turn assembler layer if BS-16 needs it under unified — NOT to the static prompt. Pest can't see the gap."). Flagging here for audit coverage and to keep it visible: it is a known, tracked, Pest-invisible behavioural gap, not a new discovery.
- **Suggested fix:** None this session — tracked in memory. If BS-16 (billing) regresses under unified, re-add as a `FynContextAssembler` per-turn layer gated on the billing bucket (parity with legacy), never back into the static prompt.

## Suggestions

### S1 — `AdviceFyn::wrapStream` logs verbatim user message at INFO (PII in application logs)
- **File:** `app/Services/AI/AdviceFyn.php:161-167`
- **Category:** 5 (Sensitive data logged)
- **What's wrong:** The new Tier-2-miss `Log::info('[AdviceFyn] LLM-fallback write-intent caught...')` includes `'message' => $message` — the raw user chat turn, which routinely contains financial PII (balances, provider names, family details). It is logged unredacted at INFO. The inline comment states this is intentional symmetry with the Tier-1 deterministic log at `:325`, so it is **consistent with the existing pattern**, not new drift. The note stands because application logs typically have a broader access surface than the `permission:admin.access`-gated `ai_messages` columns.
- **Suggested fix:** Accept as consistent-with-precedent, or (broader scope, both sites) redact/hash the message body in these classifier-telemetry logs and keep the verbatim copy only in the admin-gated DB. CSJ call — out of scope to action unilaterally.

### S2 — `zipToolRoundTrips(msg)` called inside `v-for`
- **File:** `resources/js/components/Admin/AiAudit.vue:725` (`v-for="rt in zipToolRoundTrips(msg)"`)
- **Category:** 4 (Complexity / minor perf)
- **What's wrong:** A method invocation in a `v-for` expression re-runs on every re-render of the message list rather than being memoised. Admin-only view, small N, negligible today.
- **Suggested fix:** If the audit message list grows, precompute round-trips into a computed map keyed by `msg.id`. Low priority.

### S3 — `$synonyms` coercion map rebuilt inline on every `handleCreateSavingsAccount` call
- **File:** `app/Agents/CoordinatingAgent.php:40-54`
- **Category:** 1 (Consistency) / 4 (Maintainability)
- **What's wrong:** The canonical type list was correctly hoisted to the new `SAVINGS_ACCOUNT_TYPES` const (good — single source of truth for the coercion guard and the `Rule::in` validator). The parallel `$synonyms` map that feeds the same coercion was left as a method-local array, rebuilt per call and not unit-testable in isolation.
- **Suggested fix:** Optionally hoist `$synonyms` to a sibling `private const SAVINGS_ACCOUNT_TYPE_SYNONYMS` for symmetry with `SAVINGS_ACCOUNT_TYPES` and direct testability. Cosmetic; the logic is correct as-is.

### S4 — Unused `catch` binding in `prettyJson`
- **File:** `resources/js/components/Admin/AiAudit.vue:770` (`catch (e)`)
- **Category:** 2 (Dead/redundant code)
- **What's wrong:** `e` is bound but never referenced; the handler returns a `String(value)` fallback (not a silent swallow — acceptable). Pure cosmetic.
- **Suggested fix:** Use the optional-catch-binding form `catch { ... }`. Trivial.

## Verified clean (checked, no issue — recorded so they aren't re-audited)

- **`grok-4-1-fast` → `grok-4.3` comment changes** (CoordinatingAgent:2780, AdviceFyn:263) — **factually correct**. `config/services.php:42-44` defaults to `grok-4.3`; `XaiClient`, `HasAiGuardrails`, `ConversationSummariser` all reference `grok-4.3` as "the successor to the retired grok-4-1-fast family". Comment is a correct consistency fix, not debt.
- **AiAudit PII exposure** — endpoints are admin-gated. `routes/api.php:1099` wraps the `ai-audit` group in `['auth:sanctum', 'permission:admin.access']`. New `assembled_context`/`tool_calls`/`tool_results` fields are not reachable by non-admins. (At-rest duplication is W1; transport exposure is fine.)
- **Migration** `2026_05_18_135313_add_assembled_context_to_ai_messages_table.php` — `declare(strict_types=1)`, nullable longText, reversible `down()`. Clean.
- **`AiAudit.vue` design compliance** — all colours are palette tokens (`violet-*`, `horizon-*`, `savannah-*`, `raspberry-*`, `eggshell`, `neutral-*`); no banned `amber/orange/primary/secondary/gray`. No icons added (text-only Show/Hide buttons) — Rule #16 OK. Token-count display is a metric, not a banned score (Rule #13 OK). `v-for` has `:key="rt.sequence"`.
- **`chatNavigationRouter.js`** — `META_CONVERSATIONAL` guard + word-boundary regex are well-scoped and commented; per-call regex compile is negligible; 15 vitest cases per handover. Clean.
- **`OnboardingChatDirector` try/finally** — correctly clears carried `unifiedOnboardingFocus` on the generator-throw path; no leak into the next advice turn. Correct.
- **`config/fyn.php`** default flip to `unified` — matches the CLAUDE.md canonical contract ("default `unified` post-cutover 2026-05-17; `legacy` is the emergency rollback path"). Consistent.
- **`FynTurnContext` / `FynContextAssembler` kycResult threading** — optional nullable param, backward-compatible, restores legacy Layer-9 KYC parity. Clean (this was the PR #335 Delta-2 parity fix).

---
*Generated by tech-debt-session skill*
