# Findings — Unified Fyn Prompt Audit

## Source plan
`docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md` — 10 tasks.
Spec: `docs/superpowers/specs/2026-05-16-fyn-prompt-rework-design.md`.

## Implementation map (filled during Phase 2)

| Task | Planned file(s) | Exists? | Conforms? | Notes |
|------|-----------------|---------|-----------|-------|
| 1 | config/fyn.php, FynPromptMode | YES | YES* | *Default flipped `legacy`→`unified` (post-cutover 2026-05-17, matches CLAUDE.md). Helper byte-identical to plan. Intentional. |
| 2 | FynTurnContext, ContextBucket | YES | YES+ | +Added 9th param `?array $kycResult = null` (KYC parity carrier, not in plan). Enum verbatim. Sound extension. |
| 3 | FynSystemPrompt | YES | DEVIATION | Static/nowdoc/arg-free ✓. BUT `<billing_guidance>` REMOVED from static prompt (PR #335 — moved to per-turn assembler, classification-gated). Plan Task 3 test asserted it present ×1. Deliberate cache-opt, documented in memory. Also `<preview_mode>` correctly absent (moved to assembler per plan). |
| 4 | FynCaptureTurnInstructions | YES | YES | Verbatim Layer-3 block, `%1$s`/`%2$s` slots, sprintf render. Matches plan exactly. |
| 5 | FynContextSelector | YES | YES | Byte-identical to plan. Reuses `AdviceFyn::engineCallLevelFor`. |
| 6 | FynContextAssembler | YES | DEVIATION+ | +`$orchestrateAnalysis` callable added (plan passed null → would have silently stripped financial_context every turn — real bug the plan shipped, fixed here). +KYC gate block. +billing block (PR #335 parity). `buildPrerequisiteStateContextWrapped` not `...Context`. `UserContentSanitiser` ns = `App\Services\AI\Prompts\` (plan guessed `App\Support\`). taxYear fallback dropped. |
| 7 | HasAiChat seam | TBD | TBD | |
| 8 | OnboardingChatDirector seam | TBD | TBD | |
| 9 | eval parity gate | TBD | TBD | |
| 10 | canonical + docs | TBD | TBD | |

## Key architectural reality vs plan
- Plan was written assuming `legacy` default + billing/preview IN the static
  prompt. The SHIPPED system made two cache-optimisation moves the plan did
  not anticipate: (a) billing guidance moved static→per-turn (classification
  gated), (b) preview moved static→per-turn. Both REDUCE cache-busting and
  are the right direction for the optimisation ask.
- The plan's Task 6 assembler had a latent bug (`null` orchestrator →
  `buildFinancialContext` short-circuits to sentinel → financial position
  silently absent on every advice turn). SHIPPED code fixed it via the
  `$orchestrateAnalysis` param. Confirm the caller actually forwards it (Task 7).

## Implementation audit conclusion (Phase 2 — DONE)
ALL 10 tasks implemented. 30/30 unified tests green. Parity record
(`May/May16Updates/fyn-prompt-rework-parity.md`): Step 5 = 3725/1 EXACT
parity both flags; Step 6 = 3 canonical journeys DB-verified GREEN under
unified. Canonical contract rewritten. Tag `fyn-two-prompt-pre-unify` exists.
Deviations from plan — all deliberate, documented, and IMPROVEMENTS:
- D-a: flag default flipped legacy→unified (post-cutover, CLAUDE.md/canonical).
- D-b: `<billing_guidance>` + `<preview_mode>` moved static→per-turn assembler
  (classification/preview-gated). Plan Task 3 test updated accordingly.
- D-c: `$orchestrateAnalysis` callable added to assembler+seam — fixes a
  latent plan bug (null → financial_context silently stripped every turn).
- D-d: KYC parity carrier (`kycResult` 9th VO param + assembler block).
- D-e: inline-capture focus inference (advice→delegate_to_capture path also
  gets CAPTURE bucket) — required by canonical, beyond plan.
- D-f: parity file lives at `May/May16Updates/` not `April/May16Updates/`
  (plan path typo); eval-corpus runner doesn't exist → gate redefined by
  CSJ to Step5+Step6 (documented, not fabricated).
Verdict: implementation is CORRECT and conformant. No defects found.

## Prompt waste notes (Phase 3) — MEASURED (john=data-poor, student=preview)

Static system prompt `FynSystemPrompt::text()` = **14,822 ch ≈ 3,706 tok**.
Byte-stable → Anthropic ephemeral cache hit (HasAiChat:400). BUT prod model
is grok/xAI (memory feedback_fyn_model_choice_is_deliberate) — xAI auto-
caches identical prefixes; premise holds only if prefix unchanged per turn.

Per-turn `<context>` block (NEVER prefix-cached — it is the dynamic user
turn appended AFTER the cached system prompt; full cost every single turn):

| Section | Bucket | john (poor) | student (preview) | Notes |
|---|---|---|---|---|
| `<data_completeness>` | READINESS | **695 tok** | **682 tok** | **#1 WASTE — ~55% of block, every non-factual turn, even when query isn't about gaps** |
| `<existing_records>` | POSITION | 209 tok | 60 tok | scales with record count |
| `<financial_context>` | POSITION | 40 tok | 204 tok | scales with data richness |
| `<user_profile>` | IDENTITY | 120 tok | 93 tok | every turn |
| `<known_facts>` | always | 89 tok | 89 tok | every turn |
| `<billing_guidance>` | billing-gated | 355 tok | — | only billing turns |
| `<preview_mode>` | preview-gated | — | 72 tok | only preview users |
| `<current_context>` | IDENTITY | 33 tok | 36 tok | every turn |
| `<context>` preamble | always | 28 tok | 29 tok | tax yr + name + situation |
| **TOTAL non-factual advice turn** | | **~1,233 tok** | **~1,289 tok** | re-sent EVERY turn |
| factual turn (BILLING/GENERAL) | IDENTITY only | ~280–636 tok | | data_completeness correctly absent |

**Headline:** a 10-turn advice conversation re-sends `data_completeness`
~6,900 tokens cumulatively, mostly unchanged and mostly irrelevant to the
specific question. This is the prime optimisation target. Next: read
`buildPrerequisiteStateContextWrapped` to see what those 2,778 chars are
and whether they can be gated/condensed/first-turn-only.

## Open questions for CSJ
TBD
