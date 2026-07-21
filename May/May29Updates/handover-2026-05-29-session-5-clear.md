---
type: handover
mode: context-clear
date: 2026-05-29
session: 5
branch: coala
trigger: context-handover skill (tripwire ~726k tokens)
---

# Context Clear Handover — 2026-05-29, Session 5

## Immediate state

**CoALA test-stabilisation gate is CLOSED.** Full `Unit,Feature` suite is deterministic green (4072 passed, 0 failed — confirmed across 5+ full runs). All four pre-existing dev regressions that blocked the gate are fixed, committed, and pushed to `coala`. The literal next work is **CoALA Phase 5 PR 1 — prompt-cache + GBP cost telemetry** (NOT started; fully scoped below). It must be branched off `coala` as `feat/coala-cost-telemetry`.

## The thread

1. CSJ said "move back to coala branch off dev to implement the new fyn framework." Established that **CoALA** (Cognitive Architectures for Language Agents) is the framework; the `coala` branch already held the pivot-decision doc + a test-stabilisation block but was **61 commits behind dev**.
2. CSJ confirmed (AskUserQuestion): **merge dev → coala** (done, merge `2e1dbb0`; resolved trivial CSJTODO/progress conflicts) and **follow the plan's sequence** (test-stab gate green → Phase 5 cost-telemetry PR first → Phase 1).
3. Re-ran the full suite on merged coala. The old audit flake the prior handover predicted did **NOT** dominate — instead **4 failures** surfaced (the 61 dev commits accumulated regressions that per-PR targeted runs missed — the classic "full suite not run per PR" gap). Diagnosed + fixed all:
   - **LTV overflow** (`MortgageTierCapTest`): `loan_to_value_pct decimal(5,2)` overflowed at >999.99% (random-balance factory mortgages on a low-value property). Widened to `decimal(7,2)` via raw MySQL MODIFY migration `2026_05_29_180000_widen_loan_to_value_pct_on_properties.php` (no doctrine/dbal). **Real prod robustness fix** — a single mis-entered mortgage balance on a low-value property would 500 the derived-column write.
   - **Proration** (`UpgradeSubscriptionTest`): `now()->subMonths(3)` overflows non-existent 29 Feb → rolls to 1 Mar on month-end dates → `diffInMonths` off by one. Froze test time to mid-month.
   - **Insights featured** (`InsightControllerTest`): #420 (`1dba112`) silently re-added the latest-as-featured fallback that `5d3ac7f` deliberately removed. **CSJ decided: KEEP #420's fallback** (homepage hero always has content); updated the test to expect latest-as-featured.
   - **Audit flake** (`InvestmentAccountHttpIntegrationTest`, the prior handover's known flake): unscoped `where('provider','Hargreaves Lansdown')->first()` could return another test's row (factory default provider) in InnoDB's non-deterministic no-ORDER-BY order. Scoped the lookup to the fresh per-test `user_id` — airtight.
4. CSJ said "harden the billing math." Replaced `PaymentController`'s fragile `12 - diffInMonths` proration with a **day-based `yearlyMonthsRemaining()` helper** (commit `5c018dd`). A 30-Apr yearly start reported 11 months remaining mid-June (over-billed) — now correctly 10. `round()` (not floor/ceil) provably preserves the 3-month→9 and 6-month→6 contracts. New month-end regression test added.
5. Context hit 87.5% → handover, with Phase 5 PR 1 teed up for a fresh window.

## Files touched this session

All committed to `coala` (commits `19d758b`, `bb415be`, `5c018dd`; merge `2e1dbb0`):
- `database/migrations/2026_05_29_180000_widen_loan_to_value_pct_on_properties.php` (NEW)
- `app/Http/Controllers/Api/PaymentController.php` (day-based proration + `yearlyMonthsRemaining()` helper)
- `tests/Feature/Payment/UpgradeSubscriptionTest.php` (time freeze + month-end regression test + 6-mo hard-assert)
- `tests/Feature/Api/Public/InsightControllerTest.php` (featured-fallback expectation)
- `tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` (user_id-scoped audit lookup)
- `CSJTODO.md`, `progress.md` (merge-conflict resolution, CoALA framing)

## WIP commit

- None. Tree clean (only untracked `docs/mobile/designer-brief.pdf` — CSJ's file, NOT mine, leave it). All work is in proper commits, pushed.

## Open decisions

- **None blocking.** CSJ has approved: merge dev→coala (done), follow plan sequence (test-stab → Phase 5 telemetry → Phase 1), keep #420 insights fallback, harden billing (done). Phase 5 PR 1 scope is unambiguous (below).

## Pick up from here (auto-continue contract)

1. **Branch:** `git checkout coala && git pull && git checkout -b feat/coala-cost-telemetry coala`.
2. **Build CoALA Phase 5 PR 1 — prompt-cache + GBP cost telemetry** (the plan's mandated FIRST CoALA PR; establishes the prefix-cache baseline BEFORE Phase 1 touches the assembler). Spec: `fynla-coala-implementation-plan.md` v0.4 (FR-M12/M13 in `May/May27Updates/PRD-coala-phase-5-decision-loop.md` §5, §8 internal-sequencing item 1). Scope (already audited this session):
   - **Capture gap:** `app/Traits/HasAiChat.php` ALREADY captures Anthropic `cache_read_input_tokens` (hits, ~line 426-431) and xAI `promptTokensDetails->cachedTokens` (~line 370-371), lumped into one `$totalCachedTokens` → `ai_messages.metadata.cache_hit_rate`. It does **NOT** capture `cache_creation_input_tokens` (the write-through/MISS tokens). Add that capture for both providers.
   - **Persist** `cache_hit_tokens` (= cache_read) and `cache_miss_tokens` (= cache_creation) as DISTINCT fields. Decide storage: `ai_messages.metadata` JSON extension (no migration) vs new columns (migration). PRD FR-M11 says "TBD by storage volume" — `metadata` JSON is the lighter first step; recommend metadata JSON for PR 1.
   - **GBP cost:** new `config/ai_pricing.php` (per-model £/1k input+output, + cache-read/cache-write rates if modelling them). Compute `gbp_cost` per turn from token counts × price table. Store alongside (metadata or column).
   - **Both provider paths:** Anthropic (`RawMessageStartEvent` usage) AND xAI (`response->usage`). Verify xAI surfaces a cache-creation equivalent; if not, record 0/null and note it.
   - Persistence sites: `app/Traits/HasAiChat.php` ~line 798-806 (`$assistantExtra` / `$messageMetadata` that flows into `saveMessage`). Find where `cache_hit_rate`/`$messageMetadata` is currently built (grep `messageMetadata` + `cache_hit` in HasAiChat).
   - **TDD:** write tests asserting the metadata fields + gbp_cost are persisted for a mocked LLM turn. Mirror existing AI-message persistence tests.
3. **Do NOT** touch `FynSystemPrompt::text()` (prefix-cache byte-invariance — see canonical contract). PR 1 is telemetry-only; no assembler/prompt changes.
4. PR → dev? **NO** — CoALA PRs target `coala` (the framework integration branch), per the pivot. Confirm with CSJ whether PRs go feature→coala→dev or feature→dev. (Prior CoALA handover implied coala is the working integration branch, not yet merged to dev.)
5. Loop until the new telemetry tests are green; run the full `Unit,Feature` suite once before declaring done (the gate is green now — keep it green).

## What the next Claude needs to know

- **Gate is GREEN — do not re-run the full suite to "check" before starting; it's confirmed (4072 passed, 0 failed, 5+ runs).** Only re-run after you add Phase 5 code.
- **Test DB is `laravel_testing` (MySQL).** NEVER run two `pest` concurrently (DB collision). NEVER `php artisan --env=testing` (no `.env.testing`; wipes dev DB).
- **coala = dev (current) + 4 stabilisation/billing commits.** NOT merged to dev. 0/0 with origin/coala.
- **`round()` is the correct proration operator** — floor breaks 3-mo→9, ceil breaks 6-mo→6. Don't "simplify" it.
- **Flagged but NOT done (separate from CoALA):** none outstanding — billing hardening was the last flagged item and it's done.
- **Prod state (from session 4, unchanged):** fynla.org received the big public-pages/savetax/Chart.js/PipelineAsset release (#436) + is healthy. coala work is NOT deployed anywhere.
- Vite :5173, Laravel :8000. Don't `pkill -f vite`.
- The `2026_05_29_180000_widen_loan_to_value_pct...` migration is **Pending on dev/csjones/prod** — it only ran in the test DB so far. It deploys whenever coala→dev→prod eventually ships. Harmless additive ALTER.

## Branch / deploy state

- Branch: `coala`
- Behind origin: 0 · Ahead of origin: 0 (all pushed)
- Deploy status: **Not deployed** (coala is a local-only framework integration branch; dev + prod unaffected by this session's work)
