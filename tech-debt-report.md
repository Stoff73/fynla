# Tech Debt Report — Session 77 (25 April 2026)

**Scope:** session 77 commits (`1d61a47` → `04a99fa`) on `feature/fyn-persona-split` — Sprint 0.12 (hash-chain audit) + Sprint 0.13 (CoreIdentity rewrite + FCA signposting) + Sprint 0.14 (out-of-remit refusal).
**Files analysed:** 27 (15 production PHP, 1 migration, 7 tests, 2 frontend Vue/JS, 2 config/env)
**Issues found:** 4
**Severity breakdown:** 0 critical, 1 warning, 3 suggestions

---

## Critical Issues

None. The session lands one new audit chain table (correctly indexed, FK-guarded), three append sites in `executeTool` (entry / success / failure), 4 new audit tests + 2 architecture / 5 feature tests for the rewrite + 8 out-of-remit tests, and one admin Vue tab. Sweep across AI / Fyn / Onboarding / Audit / Architecture / Unit-Constants / Unit-Services-AI: 495 passing, 1943 assertions, 0 failures.

---

## Warnings

### W1 — `summariseInput` records tool input verbatim, may contain PII on the chain
- **File:** `app/Agents/CoordinatingAgent.php` (the new `summariseInput` static helper, ~lines 824-846)
- **Category:** Security / Privacy
- **What's wrong:** The `input_summary` JSON column captures every scalar field of every tool call as-is. For `capture_personal_details` that's a date of birth; for `update_profile` it includes name, employer, salary, etc. Strings >200 chars are truncated, but anything below that — full DOB, email, postcode — lands verbatim in `ai_audit_events`. Read access is gated by `auth:sanctum + permission:admin.access` (verified in `routes/api.php`), so this is bounded to admins only, but it's PII landing in a chain that explicitly cannot be mutated (mutation breaks the hash). Once on the chain, redacting later would invalidate every subsequent row.
- **Suggested fix:** Add a per-tool field redaction list inside `summariseInput` BEFORE the value reaches the chain row (e.g. `capture_personal_details.date_of_birth → 'YYYY-MM-DD'` masked, `update_profile.email → hash(email)`, `*.postcode → first 3 chars only`). Sprint 1's prompt-injection / sanitisation theme is the natural home for this. Document the residual admin-PII exposure on the chain in vault docs until then.

---

## Suggestions

### S1 — `appendAuditEvent` swallows all `Throwable` from the chain
- **File:** `app/Agents/CoordinatingAgent.php`, `appendAuditEvent` helper (~lines 803-815)
- **Category:** Robustness / Observability
- **What's wrong:** The wrapper catches `\Throwable` and logs a warning so audit failures cannot break the chat path. The trade-off is correct (forensic, not load-bearing) and intentional — but the catch-all means a real bug in `AuditChainService` (e.g. a future schema change that drops the unique index, or a deadlock storm under high concurrency) would be invisible until someone notices the chain is shorter than expected.
- **Suggested fix:** Add an alert path: when `appendAuditEvent` warns, increment a `cache()` counter (or fire a `LogAlertEvent`) so the weekly `ai:audit:verify-chain` health check (Sunday 04:30) can also report append-failure counts in its JSON output. Out of scope for Sprint 0; track in `CSJTODO.md` for Sprint 1.

### S2 — Onboarding gap-fill call sites still pass `conversation_id = null` to `executeTool`
- **Files:** `app/Services/Onboarding/OnboardingChatDirector.php` lines 1747 (`emitGapFillToolCalls`) and 2148 (inline-capture gap-fill)
- **Category:** Inconsistency with new pattern
- **What's wrong:** S0.12 added `?int $conversationId = null` to `CoordinatingAgent::executeTool`, and `HasAiChat::handle` + the parking-hydration call site in `OnboardingChatDirector` (line 965) were updated to pass `$conversation->id`. The two gap-fill call sites at lines 1747 and 2148 don't have `$conversation` in scope, so they pass null implicitly. The result: audit rows for any tool fired during onboarding gap-fill will land with `conversation_id = NULL`, which breaks the admin chain-view "by conversation" navigation for those rows. Roughly 10-30% of audit rows for onboarding-stage users.
- **Suggested fix:** Thread `AiConversation $conversation` (or just the id) through `emitGapFillToolCalls(...)` and the inline-capture variant so they can pass the id to `executeTool`. Small, mechanical change — both call sites are inside methods that already receive a `$conversation` further up the stack. Ticket for Sprint 1.

### S3 — Pre-existing: chain-tab spinner uses non-standard `border-3` Tailwind class
- **File:** `resources/js/components/Admin/AiAudit.vue` (line ~165 in my new chain-tab loader, copied from the pre-existing pattern at line 120)
- **Category:** Convention drift (pre-existing — my new code copied the existing pattern in the same file)
- **What's wrong:** Tailwind's default config doesn't expose `border-3` — only `border-1`, `border-2`, `border-4`, `border-8`. The class is a no-op, so the spinner ring renders with the default 1-pixel border instead of the intended thicker ring. The original `AiAudit.vue` had this pre-S0.12; my new chain-tab spinner copied the pattern for consistency rather than diverging. Design system rule #12 specifies `border-4` for the canonical spinner: `<div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>`.
- **Suggested fix:** Replace both `border-3` occurrences in this file with `border-4` to match the design-system spec. One-line fix; safe to ship as a follow-up cleanup commit. Not blocking.

---

## Categories with no findings

- **Duplicate code** — checked. `appendAuditEvent` / `appendAuditCompletion` / `summariseInput` / `summariseResult` / `operationFor` are non-duplicative (no other class implements audit-row construction). `OUT_OF_REMIT_PATTERNS` array in `QueryClassifier` is unique to that class. Frontend `aiAuditService.getChain` / `verifyChain` follow the existing service pattern.
- **Dead code** — none. All new methods have call sites; no commented-out blocks; no `dd()`/`dump()`/`console.log` left in.
- **PHP convention violations** — every new file has `declare(strict_types=1)`, type hints on every method parameter and return type, no `DB` facade in controllers (the audit pieces use Eloquent + `DB::transaction` correctly), no hardcoded tax values, canonical ownership enums respected (none used).
- **Vue / JS convention violations** — chain tab uses palette colours only (`raspberry-*`, `horizon-*`, `spring-*`, `violet-*`, `savannah-*`, `neutral-*`, `light-gray`); no banned `amber-*` / `orange-*` / `gray-*` for general UI; `:key` present on every `v-for`; no `v-if` + `v-for` collisions; no scores; no acronyms beyond ISA.
- **CSS** — no custom `@keyframes spin`; no hardcoded hex in style blocks (the chain tab has no `<style>` block at all); uses global `animate-spin`.
- **Complexity** — `executeTool` was already complex pre-session; my added helpers are short (longest is `summariseInput` at 18 lines). `verifyChain` is 25 lines and uses `cursor()` so memory is constant regardless of chain length. No method >50 lines added this session. `AuditChainService` is 145 lines and `AiAudit.vue` grew by ~120 lines (chain tab + tab switcher) — both well under the 500-line file split threshold.
- **Security (other than W1)** — chain endpoints verified to be inside `Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')` group at `routes/api.php:1`; HMAC key falls back to `APP_KEY` only when `AI_AUDIT_HMAC_KEY` env is unset (production must override); `verifySignature` uses `hash_equals` for constant-time comparison; tamper-detection test confirms even single-byte mutations break the chain.
- **Inconsistency with existing patterns** — `appendAuditEvent` follows the same swallow-into-`Log::warning` pattern used by `AdviceReviewService` and the financial context cache fallback in `AdvicePromptBuilder`. New `AiAuditEvent` model mirrors `AiRequestIdempotency` model conventions (no `updated_at`, foreign keys, JSON casts).

---

*Generated by tech-debt-session skill — 2026-04-25*
