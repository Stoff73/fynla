---
type: audit + optimisation
date: 2026-05-19
branch: fynPromptRework
plan: docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md
spec: docs/superpowers/specs/2026-05-16-fyn-prompt-rework-design.md
verdict: implementation CORRECT & conformant — 0 defects; prompt waste identified & quantified
---

# Unified Fyn — Implementation Audit + Prompt Optimisation

Two deliverables in one document:

- **Part A** — every task in the 10-task plan mapped against the code as it
  is now, with conformance verdicts.
- **Part B/C** — measured token waste in the system / context prompts and a
  ranked, diff-level optimisation plan with token deltas.

---

## Part A — Implementation conformance map

Source of truth: `2026-05-16-fyn-prompt-rework.md` (Tasks 1–10).
Method: read every shipped file, ran the 30 unified Pest tests
(`tests/Unit/Services/AI/Fyn/` + the two seam tests) → **30 passed**.
Cross-checked the parity record `May/May16Updates/fyn-prompt-rework-parity.md`.

| Task | Planned artefact | Status | Verdict |
|------|------------------|--------|---------|
| 1 | `config/fyn.php` + `FynPromptMode` | Shipped | **CONFORMS** — helper byte-identical to plan. Default flipped `legacy → unified` (post-cutover 2026-05-17). Deliberate, matches CLAUDE.md + canonical. |
| 2 | `FynTurnContext` VO + `ContextBucket` enum | Shipped | **CONFORMS+** — enum verbatim; VO gained a 9th param `?array $kycResult = null` (KYC parity carrier). Sound, additive, default-null. |
| 3 | `FynSystemPrompt` (static, byte-stable) | Shipped | **CONFORMS w/ documented deviation** — nowdoc, arg-free, zero interpolation residue, security + hedging clauses verbatim. `<billing_guidance>` and `<preview_mode>` were **removed** from the static prompt and moved to the per-turn assembler (classification/preview gated). Plan Task 3 test asserted them present; test was updated to match. This is a *cache optimisation* and is the correct direction (see Part C). |
| 4 | `FynCaptureTurnInstructions` | Shipped | **CONFORMS** — Layer-3 capture block verbatim, only `%1$s`/`%2$s` slots, `sprintf` render. Multi-entity rule + ≤15-word guardrail intact. |
| 5 | `FynContextSelector` (4-bucket) | Shipped | **CONFORMS** — byte-identical to plan. Reuses `AdviceFyn::engineCallLevelFor` as the factual signal (no taxonomy redefinition). |
| 6 | `FynContextAssembler` | Shipped | **CONFORMS+ (fixes a plan bug)** — added `?callable $orchestrateAnalysis` param. The plan's Task 6 code passed `null` to `buildFinancialContext`, which short-circuits to its "analysis unavailable" sentinel — i.e. the plan as written would have **silently stripped the user's financial position from every advice turn**. Shipped code forwards the real closure. Also adds the KYC + billing parity blocks; resolves `UserContentSanitiser` to its real namespace (`App\Services\AI\Prompts\`, not the plan's guessed `App\Support\`). |
| 7 | `HasAiChat` advice seam | Shipped | **CONFORMS+** — `buildSystemPrompt` returns `FynSystemPrompt::text()` under unified; `injectUnifiedTurnContext` rewrites the last user message in-memory (persisted row untouched); forwards the sized-analysis closure; captures `assembledContext` for the admin forensic view; resets focus in `clearChatOverrides`. |
| 8 | `OnboardingChatDirector` seam | Shipped | **CONFORMS+** — `resolveUnifiedRestrictedPrompt` branches correctly; focus set before delegation and cleared in `finally` (covers the throw path). **Beyond plan:** the inline-capture path (advice → `delegate_to_capture` → `handleInlineCapture`) also infers + carries focus (`inferFocusesFromEntityTypes`), so advice-mode writes get the CAPTURE bucket. Required by the canonical handoff contract; the plan omitted it. |
| 9 | Eval parity gate | Shipped (gate redefined) | **CONFORMS w/ documented decision** — the plan's eval-corpus instrument (`EvalRunner::run`) is a pre-existing Sprint-1 scaffold hard-error and never landed runnable. CSJ redefined the gate to **Step 5** (full 3725-test suite under both flags → *exact* parity 3725/1) **+ Step 6** (3 canonical Playwright journeys, DB-verified GREEN under `unified`). Recorded honestly in `May/May16Updates/fyn-prompt-rework-parity.md` — no fabricated eval number. (Plan's path `April/May16Updates/` was a typo; file is under `May/`.) |
| 10 | Canonical rewrite + doc consolidation + tag | Shipped | **CONFORMS+** — `00-canonical.md` rewritten to the unified contract; `prompts/fyn-system-prompt.md` created; per-state docs archived under `prompts/archive/`; tag `fyn-two-prompt-pre-unify` exists. **Beyond plan:** canonical adds the CSJ 2026-05-18 directive that *both* architectures are retained **permanently** (no cleanup/deletion sub-task) — the flag is a durable A/B switch, not a migration shim. |

### Part A verdict

**The unified Fyn architecture is implemented correctly and conformantly.
Zero defects found.** Every deviation from the written plan is deliberate,
documented (in canonical / memory / the parity record), and an *improvement*
on the plan — including one case (Task 6) where the shipped code fixes a
latent bug the plan would otherwise have shipped.

The two-Fyn write-isolation guarantee, the FCA invariants, the byte-stable
static prompt, and the security clause are all intact and test-locked.

---

## Part B — Measured prompt waste

Heuristic: ~4 chars/token. Measured live against seeded users
(`john@example.com` = data-poor; `preview_student` = preview persona) via
`FynContextAssembler::build()` with the real sized-analysis closure.

### B.1 — What is cached vs what is paid every turn

| Layer | Size | Cached? | Cost model |
|---|---|---|---|
| `FynSystemPrompt::text()` (static) | **14,822 ch ≈ 3,706 tok** | Yes — byte-stable, Anthropic `cache_control: ephemeral` (`HasAiChat.php:400`); xAI auto-caches identical prefixes | Paid once per 5-min cache window |
| `<context>…</context>` + `<user_message>` (per-turn) | **~1,233–1,289 tok** | **No** — it *is* the dynamic user turn, appended *after* the cached prefix | **Paid in full on every single turn** |

The per-turn block is the cost centre: a 10-turn advice conversation pays
the per-turn block ~10× (~12,000–13,000 tokens cumulative) while the 3,706-token
system prompt is paid roughly once.

### B.2 — Per-turn block decomposition (non-factual advice turn)

| Section | Bucket | Tokens (john) | Tokens (preview) | Dynamic? |
|---|---|---:|---:|---|
| `<data_completeness>` | READINESS | **695** | **682** | **~15% dynamic, ~85% static rule text** |
| `<existing_records>` | POSITION | 60–209 | 60 | Fully dynamic (scales w/ records) |
| `<financial_context>` | POSITION | 40 | 204 | Fully dynamic (scales w/ wealth) |
| `<user_profile>` | IDENTITY | 120 | 93 | Fully dynamic |
| `<known_facts>` | always | 89 | 89 | Mostly dynamic + 1 static trailer line |
| `<current_context>` | IDENTITY | 33–36 | 36 | Route-mapped (small) |
| `<context>` preamble | always | 28 | 29 | Dynamic (tax yr, name, situation) |
| `<billing_guidance>` | billing-gated | 355 | — | Static (only on billing turns) |
| `<preview_mode>` | preview-gated | — | 72 | Static (only preview users) |
| **Non-factual advice total** | | **~1,233** | **~1,289** | |
| Factual turn (e.g. `general`) | IDENTITY only | ~280 | — | `data_completeness` correctly absent |

### B.3 — The smoking gun: `<data_completeness>`

`AdvicePromptBuilder::buildPrerequisiteStateContextWrapped()` emits ~695
tokens. Only the **module-readiness bullet list (~100 tok) is per-user
dynamic.** The remaining **~595 tokens are byte-identical for every user
and every turn** — three static rule sub-blocks:

- `NAVIGATION RULES:` (3 numbered rules)
- `RULES FOR BLOCKED MODULES:` (5 numbered rules)
- `MODULE DEPENDENCY GUIDANCE:` (Estate/Holistic/Protection/Retirement/Investment dependency prose)

This static instructional text is sitting **inside the uncached per-turn
block**. It is re-tokenised and re-billed on every non-factual advice turn,
forever, despite never changing. This is a textbook cache-busting
anti-pattern (static content riding the dynamic channel). It is **inherited
verbatim from legacy** — the rework did not introduce it — but the unified
architecture (cached static prompt + lean per-turn context) is precisely
the right place to fix it.

---

## Part C — Optimisation plan (ranked by token saved / risk)

> All numbers are tokens removed from **every non-factual advice turn**
> (i.e. multiplied by conversation length). Static-prompt growth is paid
> ~once per cache window, not per turn.

### C1 — Relocate the 3 static rule sub-blocks out of `data_completeness`  ⭐ highest value, lowest risk

- **Move** `NAVIGATION RULES` / `RULES FOR BLOCKED MODULES` /
  `MODULE DEPENDENCY GUIDANCE` from `buildPrerequisiteStateContextWrapped()`
  into `FynSystemPrompt::text()` (inside `<tool_use>`, adjacent to the
  existing navigation guidance).
- **Keep** only the dynamic per-user module-readiness list in the per-turn
  `<data_completeness>` block.
- **Per-turn saving: ~595 tok × every non-factual advice turn.**
- Static prompt grows ~595 tok (cached → paid ~once/window).
- **Behaviourally neutral**: identical text still reaches the model, just
  in the cached prefix instead of the per-turn suffix.
- **Risk: LOW but not zero** — touches `FynSystemPrompt` (re-snapshot +
  `FynSystemPromptTest` "block exactly once" assertion needs the new tags)
  and changes the *structure* (not content) of what unified sends per turn.
  The canonical contract is `unified ≡ legacy` behaviourally and the parity
  record asserts exact 3725/1; this change must be re-run under both flags
  and the parity record amended. Legacy's `buildPrerequisiteStateContextWrapped`
  stays byte-identical (legacy path untouched) so legacy parity is preserved.

### C2 — Gate `data_completeness` to navigation/blocked-relevant turns

The readiness list is only actionable when the user is navigating or asking
about a module that might be blocked. On a pure analysis turn
(`retirement_readiness` etc.) the full module-readiness matrix is rarely
needed. Option: include `<data_completeness>` only when classification is
`NAVIGATION` or when the queried module is `BLOCKED`; otherwise emit a
one-line "all required modules ready" / "Goals blocked" summary.

- **Per-turn saving: up to ~100 tok** (the dynamic remainder, on the
  majority of advice turns).
- **Risk: MEDIUM** — changes what the model sees on analysis turns; needs
  eval/browser re-verification that blocked-module handling still triggers.

### C3 — De-duplicate the static system prompt (cached, so lower priority)

`FynSystemPrompt::text()` has internal redundancy (paid once/window, but
still worth tightening):

- "Advice Fyn is read-only / never call `create_*`/`update_*`/`delete_*`"
  is stated in `<available_actions>`, again in `<handoff_guidance>`, and
  implied in `<identity>`. Consolidate to one authoritative statement in
  `<handoff_guidance>` (the TOP-PRIORITY block) + one pointer.
- The acronym expansion list in `<instructions>` (~150 tok) duplicates
  CLAUDE.md Rule #10 intent — keep (model needs it) but it is the single
  largest line; consider trimming the rarer pairs.
- `<personality>` "Always signpost regulated advice when… 'what should I
  do?'" overlaps `<fca_signposting>` and `<regulatory_compliance>` rule 3.
- **Saving: ~250–400 tok off the cached prefix** (improves cold-cache /
  cache-miss turns and any non-caching provider path).
- **Risk: HIGH** — every sentence here is compliance/security-load-bearing.
  Canonical: *"DO NOT reword compliance/security sentences. Any change must
  be re-validated against the Fyn eval suite."* Treat as a separate,
  carefully-evaled workstream, not a quick edit.

### C4 — Trim `known_facts` / preamble micro-waste

Minor: the `known_facts` trailing instruction and the `<context>` preamble
are small and mostly necessary. ~10–20 tok available. Not worth the churn
on its own; fold into C1 if touching the assembler anyway.

### Projected impact (typical 8-turn advice conversation, data-poor user)

| | Now | After C1 | After C1+C2 |
|---|---:|---:|---:|
| Per-turn context | ~1,233 tok | ~640 tok | ~540 tok |
| × 8 turns | ~9,864 tok | ~5,120 tok | ~4,320 tok |
| Static prefix (≈once/window) | 3,706 tok | ~4,300 tok | ~4,300 tok |
| **Net conversation tokens** | **~13,570** | **~9,420 (−31%)** | **~8,620 (−36%)** |

C1 alone is the dominant win and the safest (pure relocation, legacy path
untouched, behaviourally neutral). C2 is a behavioural change requiring
eval/browser sign-off. C3 is a high-care compliance workstream.

---

## Part D — Risks & guardrails for the optimisation

1. **Parity contract.** Canonical asserts `unified ≡ legacy` and the parity
   record is exact 3725/1. C1 changes unified's per-turn *structure*. Re-run
   `./vendor/bin/pest --testsuite=Unit,Feature,Architecture` under **both**
   `FYN_PROMPT_ARCH` values and amend the parity record. Legacy
   `buildPrerequisiteStateContextWrapped` must stay byte-identical.
2. **Byte-stability.** Any text added to `FynSystemPrompt::text()` must keep
   it arg-free/static; re-generate `docs/superpowers/specs/fyn-system-prompt.snapshot.txt`
   and update `FynSystemPromptTest`'s per-tag count assertion.
3. **Compliance.** C3 (and any rewording in C1's moved blocks) is
   compliance-load-bearing — wording must move *verbatim*, never reworded,
   and be browser-verified (Rule #15) before claiming done.
4. **Provider caching.** The "static = cheap" premise is strongest on
   Anthropic (explicit `cache_control`). Production model is grok/xAI
   (deliberate, per memory) — xAI auto-caches identical prefixes, so C1's
   relocation still helps, but the win is largest where the prefix is stable
   across the whole session. C1 keeps the prefix stable; C2 does not affect it.

---

## C1 — EXECUTED (CSJ-approved 2026-05-19)

CSJ chose **C1 only**. Implemented and verified:

**Change set:**
- `FynSystemPrompt.php` — added `<data_completeness_rules>` inside
  `<tool_use>` (NAVIGATION / BLOCKED-MODULE / MODULE-DEPENDENCY rules,
  byte-verbatim from `buildDataCompletenessBlock` + a one-line
  cached-context bridge sentence).
- `AdvicePromptBuilder.php` — new `buildPrerequisiteStateContextLean()`
  (per-user READY/BLOCKED matrix only). Legacy `buildDataCompletenessBlock`
  / `buildPrerequisiteStateContextWrapped` / `build():174-175`
  **byte-identical, untouched**.
- `FynContextAssembler.php` — READINESS bucket now calls the lean method.
- `fyn-system-prompt.snapshot.txt` — regenerated (14,822 → 17,282 bytes).
- `FynSystemPromptTest.php` — `<data_completeness_rules>` added to the
  per-tag-count list + a new verbatim-relocation assertion.

**Measured result (john, non-factual advice turn):**

| | Before C1 | After C1 | Δ |
|---|---:|---:|---:|
| Per-turn context block | ~1,233 tok | **674 tok** | **−559 tok/turn (−45%)** |
| `<data_completeness>` (per-turn) | ~695 tok | 133 tok | −562 tok |
| Static prompt (cached, ≈once/window) | 3,706 tok | ~4,320 tok | +614 tok (cached) |
| Legacy `buildDataCompletenessBlock` | 692 tok | 692 tok | unchanged (parity preserved) |

**Verification:**
- 31/31 unified Fyn Pest tests green (incl. byte-stable snapshot test +
  new C1 test).
- Both-flag parity on prompt-path suites (`Feature/Fyn`,
  `Feature/Onboarding`, `Unit/Services/AI`, `Unit/Services/Onboarding`):
  **identical `1 failed / 1 skipped / 624 passed` under both flags** —
  EXACT parity. The lone failure is the pre-existing stranded-cassette
  `CassetteModelProvenanceTest:77` (handover-12 C1 tech-debt item, fails
  identically under byte-identical legacy → orthogonal, not a C1
  regression).
- Rule #15 browser (unified, `john@example.com`, local MFA): advice turn
  ("How is my pension doing?") → personalised/hedged/honest, no leaks;
  navigation turn ("show me my estate planning") → URL changed to
  `/estate` via `navigate_to_page`, "Navigating to Estate Planning."
  (plain name, no route leaked) — the relocated NAVIGATION RULES drive
  correct behaviour from the **cached** prompt. Both GREEN.

**Status:** C1 complete and verified on branch `fynPromptRework`
(uncommitted — awaiting CSJ commit instruction per branch workflow; not
deployed). C2 (behavioural, eval+browser sign-off) and C3 (compliance-eval
project) remain open scoped workstreams pending CSJ decision.
