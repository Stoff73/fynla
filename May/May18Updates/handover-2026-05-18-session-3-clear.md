---
type: handover
mode: context-clear
date: 2026-05-18
session: 3
branch: fynPromptRework
trigger: context-handover skill (tripwire >90% of 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 3

## Immediate state

Just fixed the Delta-2 KYC parity bug under the unified prompt (code + tests + docs corrected, verified under both `FYN_PROMPT_ARCH` flags at the targeted-suite level), and CSJ then issued a NEW directive that needs actioning next session: **do not delete the legacy prompt system — archive it and keep both switchable for real A/B comparison.**

## The thread

- Session ran `/vault-sync` (Haiku subagent) → then CSJ asked to fix 100 orphaned May vault files + follow `VAULT-SYNC-PENDING.md`. Both done: durable `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/` area created (canonical + map + delta copies, wikilinked to Auth.md + 10-NEW-SYSTEMS.md), May Index "Handover & Deploy Archive" subsection added (0 orphans), flag marked carried.
- CSJ asked me to explain Delta 1 (3-part dispatch predicate) and Delta 2. I initially mis-framed it with "two Fyns / two systems / wasted compute / pick (a) or (b)" language. **CSJ corrected hard:** there is ONE Fyn, ONE unified prompt; the branch's whole job is collapsing the two old prompt builders into one. Parity (`unified` ≡ `legacy`) is the contract; KYC/checks/context are all REQUIRED under unified, nothing optional, nothing removed.
- Re-grounded in the real plan/spec: `April/April24Updates/spec/00-canonical.md` + `May/May16Updates/fyn-prompt-rework-parity.md`. Confirmed Delta 2 was a **real parity regression**, not a decision: legacy `AdvicePromptBuilder` emits KYC `prompt_text` as Layer 9 (`:195-198`) unconditionally; unified discarded `$kycResult` and only reproduced the `<data_completeness>` block.
- **Fixed Delta 2** (4 edits, legacy path byte-untouched): `FynTurnContext` +`?array $kycResult`; `HasAiChat::chat` passes `$kycResult` into injector; `injectUnifiedTurnContext` threads it into `FynTurnContext::make()`; `FynContextAssembler::build` emits `kycResult['prompt_text']` with the exact legacy guard, unconditional on bucket, legacy ordering. Added 2 regression guards to `FynContextAssemblerTest`.
- Verified: Pint+lint clean; `KycGateCheckerTest` + `tests/Unit/Services/AI/Fyn/` identical pass counts under both flags (31/31, 8/8 incl. new guards). Corrected the delta doc + vault index + May Index line (Delta 2 reframed decision→fixed; Delta 1 still open as doc-only).
- **Final CSJ message (the open directive):** keep the old/legacy system — archive, do not delete — so both architectures stay switchable to measure real improvement.

## Files touched this session

```
app/Services/AI/Fyn/FynTurnContext.php             |  3 +   (kycResult field/param)
app/Services/AI/Fyn/FynContextAssembler.php        | 11 +   (KYC Layer-9 parity emit)
app/Traits/HasAiChat.php                            |  3 +   (thread $kycResult unified)
tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php | 34 + (2 parity guards)
May/May18Updates/fyn-canonical-vs-implementation-delta.md | 64 ~ (Delta 2 → fixed)
May/May18Updates/VAULT-SYNC-PENDING.md             |  5 ~   (carry marked done)
```
Plus vault-only edits (not in git): `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/` (4 files), `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture.md`, `fynlaBrain/May/May Index.md`, `fynlaBrain/Current State/Auth.md`, `fynlaBrain/Architecture/v083/10-NEW-SYSTEMS.md`.
Unrelated pre-existing change swept into WIP: `fynlaFeatuuresModules` → `fynlaFeaturesModules` dir rename (NOT this session's work — typo-fix rename done outside; next session can leave it or split it out).

## WIP commit

- SHA: `aa6e2fd` — `wip: context-handover snapshot`
- Pushed: yes (`0b05a90..aa6e2fd fynPromptRework`)

## Open decisions

- **CSJ DIRECTIVE (action next session, not yet started — blocked by tripwire):** Do NOT delete the legacy prompt system. **Archive it and keep both architectures switchable** via `FYN_PROMPT_ARCH` permanently, so legacy vs unified can be A/B compared for real improvement. This **overrides** the canonical spec end-state, which currently says the Sprint cleanup deletes `AdvicePromptBuilder` / `OnboardingPromptBuilder` / etc. Default direction of travel: amend `April/April24Updates/spec/00-canonical.md` (and the relevant sprint/cleanup plan) so the end-state is "legacy archived + flag retained permanently as an A/B switch", NOT deleted. Confirm with CSJ exactly how "archived" should look (keep in-tree behind the flag as-is = simplest and already switchable; vs move to an `archive/` namespace) before editing the spec.
- Delta 1 (dispatch predicate) still open: amend `00-canonical.md` ~line 11 to the real 3-part predicate. Doc-only, CSJ owns the spec edit.
- Formal Step-5 parity (full `Unit,Feature,Architecture` suite under BOTH flags, ~10 min each) and Step-6 (Playwright canonical journeys under `unified`) NOT yet run for the Delta-2 fix — only targeted suites were. CSJ offered the choice; not yet answered.

## Pick up from here (auto-continue contract)

1. Surface the CSJ directive above verbatim and confirm the "archived" shape (recommended default: legacy stays in-tree behind `FYN_PROMPT_ARCH`, which is already byte-intact and switchable — so the real work is a SPEC/PLAN amendment, not code deletion-prevention; nothing is currently scheduled to delete it on this branch).
2. Amend `April/April24Updates/spec/00-canonical.md` "Prompt architecture flag" section + the cleanup sub-task in the relevant sprint plan so the end-state = "legacy archived, flag retained permanently for A/B", remove any "delete AdvicePromptBuilder/OnboardingPromptBuilder" cleanup step. Mirror Delta 1's predicate wording fix in the same pass.
3. Then (if CSJ wants the formal gate closed) run Step-5 parity: `./vendor/bin/pest --testsuite=Unit,Feature,Architecture` then `FYN_PROMPT_ARCH=unified ./vendor/bin/pest --testsuite=Unit,Feature,Architecture` — expect identical pass/skip counts (baseline 3725/1 per `May/May16Updates/fyn-prompt-rework-parity.md`).

## What the next Claude needs to know

- **There is ONE Fyn, ONE unified prompt.** Do not use "two Fyns / two systems / two prompts" language — that's the OLD world this branch deletes. The "split" is only tool-list + context-block, never prompt text. CSJ corrected this twice; the canonical title says "two-Fyn" but means two *write states*, not two systems.
- Parity is the contract: `FYN_PROMPT_ARCH=unified` must be behaviourally identical to `legacy`. Any unified-only gap is a BUG to fix (mirror legacy), never an optimisation/removal decision. See memory `feedback_evals_surface_engineering_issues.md`.
- Legacy is byte-intact and already switchable today: `FYN_PROMPT_ARCH=legacy` (default) vs `=unified`. CSJ wants this preserved permanently — the threat was only the spec's *future* cleanup step, not current code.
- Spec source of truth: `April/April24Updates/spec/00-canonical.md` (37 lines). Parity record: `May/May16Updates/fyn-prompt-rework-parity.md`. Durable vault home: `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/`.
- Delta-2 fix mirrors `AdvicePromptBuilder.php:195-198` exactly (the guard `!== null && isset(...) && !== ''`) in `FynContextAssembler::build`.
- WIP `aa6e2fd` swept in an unrelated `fynlaFeatuuresModules→fynlaFeaturesModules` rename — not this session's; don't attribute it to the Fyn work.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (WIP `aa6e2fd` pushed)
- Deploy status: Not deployed (feature branch; PR #332 → dev open per session-2 handover)
