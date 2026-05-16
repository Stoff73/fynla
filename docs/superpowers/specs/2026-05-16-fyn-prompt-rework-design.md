# Fyn Prompt Rework — Design Spec

**Date:** 2026-05-16
**Branch:** `fynPromptRework`
**Status:** Approved design, pending spec review
**Supersedes (prompt layer only):** `April/April24Updates/spec/00-canonical.md` two-prompt assembly. The two-Fyn *contract* is restated below; only its enforcement mechanism changes.

---

## 1. Problem

Fyn's prompt layer is two divergent builders totalling ~1,500 lines:

- `AdvicePromptBuilder` (1,294 lines) — **12 composable layers**, classification-gated, per-user/per-turn interpolation throughout.
- `OnboardingPromptBuilder` (224 lines) — 4 layers, deliberately divergent to avoid biasing the model.

Consequences: the static prefix is never byte-identical (first name + tax year interpolated into layer 1–2), so Anthropic prefix-caching rarely hits; the 12-layer ordering carries fragile cache-positioning logic; two prompts must be kept behaviourally aligned by hand; reasoning about "what does Fyn see this turn" requires reading both builders plus the classifier.

## 2. Goal

One **static, byte-identical, fully-cached system prompt** (identity, scope, response format, compliance, security, tool-use principles) + one **dynamic user-turn assembler** that prepends only the context relevant to this message. Kill the 12-layer assembly. Preserve every FCA invariant and the two-Fyn write-isolation guarantee.

## 3. Decisions (locked with CSJ, 2026-05-16)

| # | Decision | Choice |
|---|----------|--------|
| D1 | Scope | **Prompt + turn only.** Keep dispatch branch, both directors, CoordinatingAgent handlers, audit chain, prerequisite gate, preview blocking, `delegate_to_capture`. |
| D2 | Context selection | **Lean selector**, ~4 buckets, reusing the existing `QueryClassifier` as the signal source (remap its 12 outputs; do not rebuild classification). |
| D3 | Static-ness | **Fully static system prompt, zero interpolation.** First name + tax year move into the dynamic user turn. |
| D4 | Authoring | **Restructure, preserve wording.** Deduplicate/reorganise the existing proven text; do not reword compliance/security rules. |
| D5 | Recovery | **Feature flag** `FYN_PROMPT_ARCH=legacy|unified` (default `legacy`). Old builders stay in-tree behind the flag; deleted only after `unified` proves green. Git tag `fyn-two-prompt-pre-unify` at cutover commit. |
| D6 | Canonical contract | **Rewrite** `00-canonical.md` to reflect the unified prompt (§9 below is the new contract text). |

## 4. Architecture

```
AiChatController::sendMessage            (UNCHANGED — still branches on users.onboarding_completed)
  ├─ onboarding_completed=false → OnboardingChatDirector ─┐
  └─ onboarding_completed=true  → AdviceFyn ──────────────┤
                                                          ▼
                            ┌──────────────────────────────────────────┐
                            │ FynSystemPrompt::text()    static, cached  │
                            │ FynContextAssembler(ctx)   dynamic turn    │
                            └──────────────────────────────────────────┘
```

### New units

**`app/Services/AI/Fyn/FynSystemPrompt.php`**
- `public static function text(): string`
- Zero arguments, zero interpolation. Returns a module-level constant string. Byte-identical for every user and every turn → maximal Anthropic prefix-cache hit (one cached prefix shared across the whole user base).
- One responsibility: hold the static system prompt. No dependencies.

**`app/Services/AI/Fyn/FynContextAssembler.php`**
- `public function build(FynTurnContext $ctx): string`
- Returns the `<context>…</context><user_message>…</user_message>` block prepended to the turn.
- Depends on: `FynContextSelector`, the existing context-producing services (`MemoryRetrieverService`, `PrerequisiteGateService`, `AdviceReviewService`, the `orchestrateAnalysis` callable, existing-records/financial-context builders — *reused, not rewritten; lifted out of `AdvicePromptBuilder` into focused private builders*).

**`app/Services/AI/Fyn/FynContextSelector.php`**
- `public function buckets(FynTurnContext $ctx): array` → set of `ContextBucket` enum.
- Reuses `QueryClassifier` output; maps it onto 4 buckets (§6).

**`app/Services/AI/Fyn/FynTurnContext.php`** — value object: `user`, `message`, `currentRoute`, `mode` (`advice` | `onboarding`), `onboardingFocus` (nullable), `isPreview`, `classification` (from existing `QueryClassifier`).

### Boundaries / isolation

- `FynSystemPrompt` — pure constant, testable by string assertion, no consumers can change its internals' effect.
- `FynContextAssembler` — single entry `build()`; every context sub-block is a private method with one job; consumers only see the assembled string.
- `FynContextSelector` — pure function of `FynTurnContext` → bucket set; unit-testable in isolation.
- Tool gating is **out of these units entirely** — it stays in `AdviceFyn::WRITE_TOOLS` strip + onboarding `toolsListOverride`, untouched.

## 5. The static system prompt

Assembled once from the **existing** text (D4), deduplicated, in this fixed order:

```
<identity>          generalised — "you help the user" (no {firstName})
<security>          verbatim from Prompts/CoreIdentity (9 non-negotiable rules)
<scope>             generalised (no {firstName})
<personality>       verbatim
<response_format>   verbatim; the "{firstName}" informal-address line generalised to
                    "you may occasionally use the user's first name (given in your context)"
<instructions>      verbatim from Prompts/ComplianceRules (British, no-acronyms,
                    currency, IDs, routes, joint ownership, jargon, irrelevant concepts)
<regulatory>        verbatim from Prompts/ComplianceRules; rule 5's "{taxYear}"
                    generalised to "the tax year given in your context"
<tool_use>          consolidated from Prompts/FcaProcessInstructions:
                    FCA 6-step, read-vs-write tool error handling,
                    update-vs-create rule, handoff (delegate_to_capture) rules,
                    billing response shape, FCA-signposting final-line rule
</tool_use>
```

Everything per-user / per-turn / per-classification is **removed** from the system prompt and relocated to the user turn (§6). No compliance/security sentence is reworded — only relocated or generalised at named interpolation sites. The two generalised sites (`firstName`, `taxYear`) are the *only* wording deltas; both are covered by golden conversations in the eval suite.

**Cache outcome:** the system prompt is one immutable string. Provider `system` field carries it with `cache_control` so the entire prefix is a cache hit on turns 2..N and across users.

## 6. The dynamic user turn

```
<context>
  Current tax year: {taxYear}
  You are speaking with: {firstName}
  Situation: advice  |  onboarding — focus: {focusLabel}
  {profile}                                ← always (IDENTITY)
  {current page}                           ← always (IDENTITY)
  {financial snapshot}                     ← POSITION
  {existing records}                       ← POSITION
  {ranked recommendations}                 ← POSITION
  {known facts}                            ← always when MemoryRetrieverService returns a non-empty block
  {data completeness · KYC · review-due}   ← READINESS
  {preview-mode notice}                    ← if isPreview
  {capture-turn instructions}              ← if onboarding capture turn
</context>

<user_message>
  {message, wrapped via UserContentSanitiser}
</user_message>
```

All user-controlled free text keeps the existing `UserContentSanitiser::clean()`/`wrap()` treatment at every interpolation site (names, account/scheme/provider/address/goal/trust/chattel/liability/recipient strings). This is unchanged from today.

Tools are passed via the provider `tools` API parameter exactly as today — gated by `AdviceFyn::WRITE_TOOLS` strip (advice) / onboarding `toolsListOverride` (capture). **The prompt rework does not touch tool gating.**

### The lean selector (12 layers → 4 buckets)

| Bucket | Content | Included when |
|--------|---------|---------------|
| `IDENTITY` | profile narrative, current-page context | **always** |
| `POSITION` | financial snapshot, existing records, ranked recommendations | message references the user's money, records, or asks for advice — derived from existing `QueryClassifier` (ADVICE / HOLISTIC / module-scoped primaries) |
| `READINESS` | data completeness, KYC gate result, review-due | advice-type query or a module that may be `BLOCKED` |
| `CAPTURE` | onboarding focus header, capture-turn instruction block | `OnboardingChatDirector` is mid-flow (`mode = onboarding`) |

Known-facts gap-fill (`MemoryRetrieverService::renderKnownFactsBlock()`) is **mode-independent**: emitted in both advice and onboarding turns whenever the block is non-empty, exactly as the legacy `AdvicePromptBuilder` Layer 3d / `OnboardingPromptBuilder` Layer 4 both do today. It is not gated by a bucket.

Rules:
- `mode = onboarding` ⇒ always `{IDENTITY, CAPTURE}` (never POSITION/READINESS — preserves the deliberate onboarding context-starvation that today's `OnboardingPromptBuilder` enforces, see its "Why a separate, shorter prompt?" rationale).
- `mode = advice` ⇒ `IDENTITY` + selector-chosen `POSITION`/`READINESS`.
- Factual queries (BILLING / NAVIGATION / DATA_ENTRY / OUT_OF_REMIT / INCOME / GENERAL) ⇒ `IDENTITY` only (preserves today's ~500–1000 token saving from skipping financial context).

The existing per-`(user, classification.primary)` financial-context cache (120s) and existing-records cache (60s) are retained inside the lifted builders.

## 7. Onboarding integration

`OnboardingChatDirector` keeps its full state machine — bubble flow, resume-where-you-left-off, focus journeys, multi-entity capture, retraction handling, `handleInlineCapture`. The **only** change: where it previously called `OnboardingPromptBuilder::build()`, it now calls `FynSystemPrompt::text()` for the system field and `FynContextAssembler::build()` with `mode = onboarding` for the turn.

The strict capture-turn rules (one `tool_use` per entity; ≤15-word or empty acknowledgement; no questions; ignore out-of-scope volunteered info; retraction → `update_profile`/`update_record`; tool list for the focus) move **verbatim** from `OnboardingPromptBuilder` Layer 3 into the `CAPTURE` context block emitted by `FynContextAssembler`. Wording preserved (D4).

## 8. Feature flag, archival, cutover

```php
// inside AdviceFyn and OnboardingChatDirector, at the prompt-build call site
if (config('fyn.prompt_architecture') === 'unified') {
    $system = FynSystemPrompt::text();
    $turn   = $this->fynContextAssembler->build($ctx);
} else {
    // existing path — byte-for-byte untouched
    $system = $this->advicePromptBuilder->build(/* … */);   // or OnboardingPromptBuilder
}
```

- `config/fyn.php` → `'prompt_architecture' => env('FYN_PROMPT_ARCH', 'legacy')`.
- `legacy` is default until `unified` proves green on the eval suite.
- Old `AdvicePromptBuilder`, `OnboardingPromptBuilder`, and the `Prompts/*` classes remain in-tree, **unmodified**, behind the flag — the flag *is* the archive (legacy path stays runnable in prod via env, instant rollback, no redeploy).
- Git tag `fyn-two-prompt-pre-unify` on the commit immediately before the cutover commit, for a clean reference point.
- Old code is deleted in a **separate follow-up PR**, only after `unified` is the proven default and has run clean in production.

## 9. Rewritten canonical contract

This replaces the body of `April/April24Updates/spec/00-canonical.md`. It restates the two-Fyn behavioural contract while moving its enforcement off the prompt and onto dispatch + tool gating.

> **FYN HAS ONE PROMPT, TWO WRITE STATES.**
>
> Fyn presents as one chat surface with one static system prompt. It has two *write states*, selected by `AiChatController::sendMessage` on `users.onboarding_completed` and enforced purely by which tools are in the turn's tool list — **not** by prompt content.
>
> **ONBOARDING STATE** (`OnboardingChatDirector`, `onboarding_completed = false`, plus the post-onboarding `handleInlineCapture` entry point) is the **only** state whose tool list contains `create_*` / `update_*` / `delete_*` / `set_expenditure` / `capture_*`. It runs the bubble-driven onboarding flow, accepts multi-line input, persists it, has memory (already-known facts are not re-asked but resurfaced), and resumes where the user left off. It also receives handovers from the advice state for outstanding information.
>
> **ADVICE STATE** (`AdviceFyn`, `onboarding_completed = true`) answers requests using the recommendation engine, risk module, and every other engine/module. Its tool list has **every** write tool stripped (`AdviceFyn::WRITE_TOOLS`) including `navigate_to_page`. It is read-only by construction. Write intents emit `delegate_to_capture` → `AdviceFyn::wrapStream` → `OnboardingChatDirector::handleInlineCapture` → the same `CoordinatingAgent` write handlers. The synthetic `handoff` SSE event is consumed internally and never reaches the frontend (INV-2.4.1).
>
> **THE USER NEVER SEES THE HANDOFF OR FEELS THE SWITCH.** No `persona_state_change` SSE event. No capturing pill. Input placeholder invariant. No frontend signal distinguishes the states.
>
> **The system prompt no longer encodes the split.** Both states send the identical `FynSystemPrompt::text()`. What differs per state is (a) the dynamic context block (`mode: advice` vs `mode: onboarding — focus: X`) and (b) the tool list. The read/write boundary is a tool-gating + dispatch guarantee, not a prompt instruction.
>
> No `FynPersonaOrchestrator`, no invoker, no registry, no `DataCapturePromptBuilder`, no `AdvicePromptBuilder`, no `OnboardingPromptBuilder`. `HandoffContract` constants and `CaptureContext` VO are kept.

The two existing artefacts under `prompts/` (`advice-system-prompt.md`, `onboarding-system-prompt.md`) become a single `prompts/fyn-system-prompt.md` documenting `FynSystemPrompt::text()`; the per-state docs are archived alongside the legacy code.

## 10. Testing / acceptance

No new test framework. Acceptance is the **existing eval suite** run under both flag values:

- ~35 falsifiable invariants (`01-invariants.md`)
- 75 golden conversations (`fyn-rubrics.md §B`)
- Scenario category `09-canonical-behaviour` (10 scenarios) — **any regression here blocks cutover**
- The full Pest suite (`./vendor/bin/pest`) for the new units + unchanged plumbing

**Parity gate:** `FYN_PROMPT_ARCH=unified` results must be **≥** the `legacy` baseline on every invariant and the canonical scenarios. Same suite, both flag values, is the side-by-side parity proof. Per CLAUDE.md Rule #15, loop diagnose → fix → re-verify until `unified` is green per the canonical scenarios before `unified` becomes default.

New unit tests:
- `FynSystemPrompt::text()` is byte-stable (snapshot test) and contains each required block.
- `FynContextSelector` bucket logic per mode/classification (table-driven).
- `FynContextAssembler` emits/omits each block per bucket set; sanitisation applied at every user-text site.

## 11. Out of scope (YAGNI)

Not touched: `CoordinatingAgent` handlers, audit chain, `PrerequisiteGateService` gate logic, `PreviewWriteInterceptor`, the 49 tools and their schemas, SSE event shape, the controller dispatch, the onboarding state-machine internals, `delegate_to_capture` handoff mechanics, `QueryClassifier` classification logic (reused as-is). No reworded compliance/security text. No new classification engine. No deletion of legacy code in this PR.

## 12. Risks

| Risk | Mitigation |
|------|------------|
| Generalising `{firstName}`/`{taxYear}` out of the system prompt subtly changes model behaviour | Both sites covered by golden conversations; parity gate (§10) catches regressions; values still present in every turn via context |
| Onboarding context-starvation lost when sharing infrastructure | Selector hard-rule: `mode = onboarding ⇒ {IDENTITY, CAPTURE}` only, never POSITION/READINESS (§6) |
| Lifting context builders out of `AdvicePromptBuilder` introduces behavioural drift | Builders lifted *verbatim*; legacy path retained behind flag for byte-diff comparison; parity gate |
| Recency-bias rules (handoff promoted to Layer 3b for Grok) lose their position | `<tool_use>` block placement in the static prompt preserves the "handoff rule appears early and emphatically" property; verified against BS-14 / handoff golden conversations |
