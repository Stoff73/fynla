---
type: handover
mode: context-clear
date: 2026-05-28
session: 2
branch: coala
trigger: context-handover skill (tripwire ~823k)
---

# Context Clear Handover — 2026-05-28, Session 2

## Immediate state

Mid **test-stabilisation block** on the new `coala` branch. The full suite went from **17 failed → 2 failed (4271 passed)**. Two failures remain: `MobileScaffoldTest` (root-caused, fix ready, NOT yet applied) and `InvestmentAccountHttpIntegrationTest` "records FORM audit context" (isolation bug — passes alone, fails in full suite — NOT yet diagnosed to root cause).

## The thread

- CSJ asked whether SP1 (canonical-store) was worth continuing vs pivoting to CoALA + testing. I wrote a full prioritisation review → **`May/May28Updates/SP1-vs-CoALA-prioritisation-review-2026-05-28.md`**. Verdict (CSJ-approved): **defer SP1 after Pass 6 PR 5a, pivot to CoALA, test-stabilise first.** Key finding: CoALA has **zero dependency** on the SP1 entity stores (different layers — confirmed by grepping all 6 CoALA PRDs).
- CSJ said: "Defer SP1, start the test-stabilisation block, note this, do it on a coala branch." Done: created `coala` off `dev`, recorded the defer in CSJTODO + the SP1 spec (commit `d142ff0`), SP1 resume point preserved (Pass 6 PR 5b).
- Ran the FULL suite (not just my predicted 3 areas) → found **17 failures across 5 areas**. Diagnosed each with systematic-debugging (evidence, not assumption).
- Fixed 15 of 17 (see "Files touched"). The 2 remaining are below.
- **Rejected approach (do NOT retry):** I tried a *systemic* fix — auto-seeding `TierConfiguration` in the global `tests/Pest.php` `beforeEach`. **It does NOT work** — proved via a file-write probe that the Pest.php global `beforeEach(...)->in('Feature'/'Unit/Services')` **never fires** (even the long-standing TaxConfiguration auto-seed in it is dead; tests seed tax per-test instead). Reverted Pest.php to original. **The working convention is per-test `$this->seed(TierConfigurationSeeder::class)`** — that's what I used.

## Files touched this session (WIP commit b774538)

Test-stabilisation (all on `coala`):
- `tests/Feature/Api/MortgageControllerTest.php` — +`$this->seed(TierConfigurationSeeder::class)` in `setUp` (fixes 7)
- `tests/Unit/Services/Investment/InlineHoldingsTest.php` — same (fixes 4)
- `tests/Feature/AI/DirectWrite/CreateMortgageTest.php` — same (fixes 2)
- `tests/Architecture/Phase03ArchitectureTest.php` — 2 stale `NetWorthService` assertions updated (constructor param count→type-check; `use App\Models\Property`→`use App\Services\Stores\PropertyStore`)
- `app/Services/Retirement/RetirementIncomeService.php:392` — `isa_type` fallback `stocks_shares`→`stocks_and_shares`
- `database/factories/Investment/InvestmentAccountFactory.php:58` — `isa()` state `stocks_shares`→`stocks_and_shares`

Earlier on `coala` (committed `d142ff0`): CSJTODO defer note, SP1 spec status→PAUSED, the prioritisation review doc.

## WIP commit

- SHA: `b774538` (`wip: context-handover snapshot`)
- Pushed: **yes** (`origin/coala`)
- Next session: finish the 2 remaining fixes, then squash/rename this WIP into proper commits (or just commit the 2 fixes on top and leave WIP — it's a working branch).

## Open decisions (next session proceeds with the noted default unless CSJ redirects)

1. **LISA bucketing fix** — `ISAAllowanceOptimizer.php:141,152` buckets Lifetime ISAs by the non-existent `account_type==='lifetime_isa'`; should read `isa_type==='lifetime'`. This is a real bug but **changes a financial calculation** (user-facing) and needs its own test. **Default: keep DEFERRED** as a separate follow-up (already logged in CSJTODO), NOT part of "green the suite". Task #9 part B.
2. **Dead global Pest `beforeEach`** — the `tests/Pest.php` TaxConfiguration/`->in()` auto-seed never fires (latent Pest-config issue). **Default: leave it** (out of scope for test-stab; tests seed per-test). Worth a future investigation but not now.
3. **The untracked `docs/mobile/designer-brief.pdf`** — CSJ's own file, left untracked all session. Leave it.

## Pick up from here (auto-continue contract)

1. **Apply the MobileScaffold fix** (root cause confirmed): `tests/Feature/Mobile/MobileScaffoldTest.php:15` asserts `src="'.url('/m/app').'"` but `resources/views/mobile-host.blade.php:16` correctly renders `src="{{ url('/m/landing') }}"` (per SP3 architecture: `/m` host → iframe → `/m/landing` → `/m/app`). **The TEST is stale** (the iframe target moved to `/m/landing` when the landing page was inserted). Fix: change the assertion (and its comment) from `url('/m/app')` to `url('/m/landing')`. Verify: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php`.
2. **Diagnose the isolation bug**: `tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` "records FORM audit context" (line 20-47) PASSES alone (9/9) but FAILS in the full suite. It seeds tier+tax in its own beforeEach and asserts the CREATED `AuditLog` row has `metadata['ingest_source']==='form'`. **Already ruled out:** `AuditLog::withContext` (proper try/finally push-pop of static `$dataChangeContext` — no leak); `TierConfigurationStore::$cache` (instance-scoped, not static); the `config('audit.in_tests')` gate (per-test). **NOT yet done:** get the ACTUAL in-suite error. Reproduce by running `InvestmentAccountHttpIntegrationTest` AFTER a chunk of `tests/Feature/` (bisect for the polluting test), with `withoutExceptionHandling()` on the failing assertion to surface whether it's a 404 (no account → null audit row) or a wrong/null ingest_source. Likely a static/singleton state leak from an earlier test. Loop until root-caused (systematic-debugging — do NOT guess-fix).
3. **Final full-suite run** → confirm **0 failed** (26 skipped is normal — Browser scenarios). Then commit the 2 fixes on `coala`.
4. After green: the test-stabilisation block is done → CoALA can begin (Phase 5 cost-telemetry PR first, then Phase 1 — per the review doc + `fynla-coala-implementation-plan.md` v0.4 + `May/May27Updates/PRD-coala-phase-{1..6}-*.md`).

## What the next Claude needs to know

- **Global Pest `beforeEach` is DEAD — use per-test `$this->seed(TierConfigurationSeeder::class)`.** Every entity-creation test now needs it because SP1 routed all writes through the tier gate (`DbTierGate → TierConfigurationStore::forTier()->firstOrFail()` → `ModelNotFoundException` → 404). This is THE root cause of the Class-A cluster (13 of 17 failures).
- **Run the FULL suite (`./vendor/bin/pest`) to get the real baseline** — targeted per-PR runs missed these (PR 2/3 never ran InlineHoldingsTest/CreateMortgageTest, so they regressed silently).
- **Don't run two `pest` processes concurrently** — they collide on the shared test DB and corrupt results. Background-suite + foreground-pest = bad.
- Full suite #2 (this session, after my fixes): `2 failed, 26 skipped, 4271 passed`. Capture file was `/tmp/coala-fullsuite-2.txt`.
- The prioritisation review doc (`May/May28Updates/SP1-vs-CoALA-...md`) is the canonical "why we pivoted" record — read it if CoALA sequencing questions come up.

## Branch / deploy state

- Branch: `coala` (off `dev` at `88ee9c4`)
- Ahead of origin/coala: 0 (just pushed `b774538`)
- Relationship to `dev`: `coala` = `dev` + the defer-note commit (`d142ff0`) + WIP test-stab (`b774538`). Not yet merged to `dev`.
- Deploy status: **Not deployed** (test-stabilisation only; csjones still pre-Pass-4-PR6 per the standing deploy gate)
