---
type: handover
mode: end-of-day
date: 2026-06-13
session: 1
branch: dev
previous_session: 2026-06-11 session 4 (context-clear)
---

# Handover — 2026-06-13, Session 1

## Where we left off
Track 2 (CoALA integration of the recommendation catalogue) is **fully landed on `coala`** — spec approved, 11-task plan executed subagent-driven with two-stage review per task, all PRs merged, final suite 4,963 passed / 30 known skips / 0 failed. Every loose thread CSJ approved was then closed the same day, including two real tax-engine fixes on dev. Nothing is in flight anywhere: no open PRs, no undecided flags, both branches pushed and green.

## What shipped today (2026-06-12)
**To `coala` (PRs #532–#536, #539–#540, #543, #545):**
- dev→coala step-0 merge (12 conflicts, nothing-lost proven bidirectionally) + §6f tool_schema corpus port + golden masters + full §5 gate incl. a fully green live grok-4.3 Azlan run (the old turn-6 KNOWN-RED now passes — annotation retired)
- 20-file house_view corpus (two-round compliance-grade review; all prose pinned to real code paths) + retrieval acceptance tests
- **Semantic-retriever conformance fix** — stopwords, word-boundary scoring, two-distinct-token gate; before it, every English turn loaded ~4 strategy bodies ("the" scored 56–67/file)
- Composed-plan fetch-skill (3-way shape parity + cross-path digest harmonisation), strategy-id fetch provenance (skill + tool paths), lean-prompt conformance tests, A4 synthesis-persistence test
- Planner routing procedure (`fyn-memory/procedural/recommendation-routing.md`, YAML per template) + inactive A1/A2 overlays (`active: false`; flip = one frontmatter line; needs a `provider: xai` variant at flip time)
- **Pest global hooks re-activated** in the supported form, liveness-pinned (`PestHooksLivenessTest` goes RED if they rot — CSJ's condition)
- **Test-isolation leak fixed** (18 app-swap sites committing rows outside the test transaction; dead Pest hooks root-caused) + `tests/Integration` wired into phpunit.xml (30 tests now in every run)
- `<procedures>` block relevance-filtered per turn (reasoner side only — planner deliberately unfiltered); root procedures stamp `id@version` into episode provenance; `extractStrategyIds`/`planDigest` shared home on `ComposedTaxPlanService`; `FynMemoryStore::procedures()` memoised

**To `dev` (PRs #537–#538, #541–#542, #544):**
- **#542 ISA shared allowance** — all ISA types draw from ONE annual allowance per person (greedy-by-value `IsaAllowanceAllocator`; LISA counts against both limits; spouse/Junior pools separate; tax-compliance review approved with hand-derived maths)
- **#544 Bed-and-ISA gains cap** — saving capped to gains the allowance-clipped proceeds can crystallise (was 3× overstated in the test case); + CLAUDE.md Rule 10 v1.3.0; + SUPERSEDED banners on the two legacy deploy docs
- **#537 README refresh** (v0.9.4 → v1.0 production, dead refs removed, GitHub homepage set) and **#541 DEPLOY.md** prod chain ends `config:cache`, never `optimize`
- **#538 Phailanx mobile app pages** (merged on CSJ instruction; branch lacked the contributor prefix — noted to CSJ)

## What's in flight (NOT done)
- Nothing in flight. All work merged, all branches deleted, working trees clean.

## Deploy status
**Ready to deploy but NOT deployed** — csjones is at `d0f7cf6` (2026-06-11); dev is at `7b9152b`. Catch-up steps (no migrations, no composer changes; needs main + mobile bundle rebuilds): `June/June13Updates/deploy-2026-06-13.md`. CSJ decides when.

## Tech debt found this session
Per-PR two-stage reviews (10+ dispatches) replaced the end-of-session audit; all findings were fixed in-loop. Remaining recorded items:
- Optional: `gia-rebalance.md`/`gia-to-spouse.md` corpus titles are similar (retrieval distinguishes them today — watch if more GIA strategies are authored).
- The `<procedures>` filter means future root procedures should carry natural `applies_when` vocabulary — noted in the store docblock.

## Known issues / blockers
- None red. The `SavingsAgentGoalsTest` dev flake is de-flaked (coala's fix ported in #542's branch).
- `tests/E2E` is a Playwright (JS) suite — cannot live in phpunit.xml by design; runs via node only.

## Rules reinforced this session
- Failing acceptance assertions = fix the code, never the assertion (the retriever conformance fix came from refusing to game a test query) — existing `feedback_evals_surface_engineering_issues.md`.
- New memory: `project_track2_landed_on_coala.md` — supersedes the stale "awaiting merge" claims in the older coala memories. MEMORY.md trimmed 28.5KB → 17.7KB (was over the load budget).

## Next session should
1. **CSJ decisions first:** deploy dev → csjones when ready (deploy note above); then the carried acceptance items — re-test the Azlan journey on csjones, eyeball the gamified web dashboard, the insights-featured judgement call.
2. **The coala→dev landing programme** is the next major arc (its own spec/plan per the Track 2 spec §7) — `project_track2_landed_on_coala.md` + `git log origin/coala` are the starting truth. Worktree ready at `~/Desktop/fynla-coala`.
3. If Fyn work continues on coala: the FR-M2 `<procedures>` block rides every reasoner turn by design — if CSJ wants planner-only consumption, it's a small assembler change (offered in PR #535, not requested).
4. Flip-to-active for A1/A2 overlays needs the `provider: xai` variants (the live app runs xAI).
5. Vault: `UKTaxes.md` Current State doc still stale (carried; touched again by today's ISA work).

## Context hints
- Active branch type: mainline (dev @ `7b9152b`; coala @ `10193e2`, both pushed)
- Behind origin: 0 — both up to date
- Uncommitted: only the two long-standing untracked docs (`docs/mobile/designer-brief.pdf`, `docs/security/security-review-2026-06-09.md`) — left deliberately, as every prior session has
- Last commit: `7b9152b` docs(track2): the executed Track 2 implementation plan
