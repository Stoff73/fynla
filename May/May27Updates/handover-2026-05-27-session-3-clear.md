---
type: handover
mode: context-clear
date: 2026-05-27
session: 3
branch: dev
trigger: context-handover skill (tripwire at ~739k tokens)
previous_session: 2026-05-27 session 2 (end-of-day)
---

# Context Clear Handover — 2026-05-27, Session 3

## Immediate state

**SP1 Pass 4 (Properties canonical store) is COMPLETE.** Just merged PR #402 (`c972fff`) which closes the 8-PR sequence. PropertyStore is fully shipped — joins Savings, R1-R4 reference data, and Pensions as a finished entity store. Sub-Project 1: **7 of 19 entity stores done.**

Working tree clean on `dev` at `c972fff`. Up to date with origin. csjones is one deploy behind (PR 6/7/8 + minor docs).

## The thread

Pass 4 went from 3/8 PRs at session start to 8/8 PRs in a single session via the subagent-driven-development workflow. Major arc:

- **Morning csjones deploy** (`6dcfba67`) to unblock PR 4 dispatch.
- **PR #390 (PR 4 — upload + onboarding + seeders)** shipped same-session via Sonnet implementer → 2 Opus reviewers → in-flight Minor #1 fix (added `PropertyNormaliser::fromForm` seam in OnboardingService) → admin-merge.
- **PR #395 (5a — Estate/IHT reads)** introduced a **Major joint-aware-store regression** caught by code-quality review: `PropertyStore::forUser` is joint-aware (`Property::forUserOrJoint($user->id)->get()`) but the name reads like Eloquent's conventional primary-only `forUser`. The implementer used `forUser` for 7 sites that originally used `Property::where('user_id', $userId)` — silently broadened to primary-OR-joint, causing joint-property double-count in IHT projection when `dataSharingEnabled=true`. Fix: chain `->where('user_id', $user->id)` on the Collection. Added **`PropertyReadConsumerParityTest`** (7 cases) to lock the contract for all subsequent 5b-5e clusters.
- **PRs 5b/5c/5d/5e** all consumed the locked parity contract correctly. **5c** added a new store method (`forTrust(int $trustId)`) for `TrustAssetAggregatorService`. **5d** had complex patterns (lazy mortgages eager-load, SQL `whereRaw` → PHP Collection postcode filter). **5e** was tiny (1 site + 2 class-name residuals).
- **PR #400 (PR 6 — derived columns + snapshot table)** added `current_value_gbp`, `equity_gbp`, `loan_to_value_pct` + `PropertyValueSnapshot` table + calculator + backfill command + 2 snapshot policies. Implementer correctly verified `SnapshotPolicy`'s real Closure-based constructor (plan's named-params were illustrative).
- **PR #401 (PR 7 — tier-cap test)** — single test file, 5 cases. Handled directly without subagent.
- **PR #402 (PR 8 — lock-down + audit + parity + Store.md)** — final Pass 4 PR. Boundary test rewritten with LOCKED framing (no transition language). `PropertyAuditIngestSourceTest` (5 cases). `PropertyThreeIngestParityTest` (2 cases including `tenants_in_common`). 195-line `PropertyStore.md`. Quirk #11 added in-flight per spec reviewer's recommendation.

**Spec doc updated** in session: `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md` brought up to date through PR #390 (commit `6dcfba6`). NOT yet updated for PRs 5a-5e/6/7/8 — that's a follow-up.

**Implementer truncations observed:** Sonnet subagents hit truncation mid-task on PRs 5b, 5c, 5d. Each time it was the formatter import-removal pattern (constructor DI edit landed, but `use App\Services\Stores\PropertyStore;` got dropped because the import wasn't yet "used" when the formatter ran). Resumed via `SendMessage` with explicit "add import in same edit" guidance. PR 5d in particular — main thread had to manually finish + the implementer never came back; tests + commit + push happened directly.

## Files touched this session

Across PRs #390 / #395 / #396 / #397 / #398 / #399 / #400 / #401 / #402 + CSJTODO + spec:

**Production code:**
- `app/Services/Stores/PropertyStore.php` — gained `forTrust`, `recalculateDerived`, constructor extended with 2 deps (calculator + snapshot policies)
- `app/Services/Stores/Normalisers/PropertyNormaliser.php` — `fromForm` seam used in OnboardingService (PR 4)
- `app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php` — NEW (PR 6)
- `app/Services/Stores/Snapshots/SnapshotPolicies.php` — extended with `propertyValue` + `propertyEquity`
- `app/Models/Property.php` — fillable + casts for 6 new derived-column fields
- `app/Models/PropertyValueSnapshot.php` — NEW (PR 6)
- `app/Console/Commands/BackfillPropertyDerivedColumns.php` — NEW (PR 6)
- ~17 read-consumer service files across Estate/IHT/UserProfile/AI/Tax/Trust/Coordination/NetWorth/Mobile/Shared — all Property reads now routed through PropertyStore

**Tests:**
- `tests/Feature/Stores/PropertyReadConsumerParityTest.php` — NEW (PR 5a) — locks joint-aware contract, 7 cases
- `tests/Feature/Stores/PropertyDerivedColumnsBackfillTest.php` — NEW (PR 6)
- `tests/Feature/Stores/PropertyTierCapTest.php` — NEW (PR 7), 5 cases
- `tests/Feature/Stores/PropertyAuditIngestSourceTest.php` — NEW (PR 8), 5 cases
- `tests/Feature/Stores/PropertyThreeIngestParityTest.php` — NEW (PR 8), 2 cases (individual + tenants_in_common)
- `tests/Unit/Services/Stores/Recalc/PropertyDerivedColumnCalculatorTest.php` — NEW (PR 6), 4 cases
- `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` — modified in EVERY PR, finally LOCKED in PR 8
- 2 new migrations (PR 6): `2026_05_27_100000_add_derived_columns_to_properties.php`, `2026_05_27_100001_create_property_value_snapshots_table.php`

**Docs:**
- `app/Services/Stores/PropertyStore.md` — NEW (PR 8), 11 per-entity quirks, full API tables
- `CSJTODO.md` — updated 5 times across the session (after every cluster milestone)
- `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md` — brought up to date through PR #390 only

## WIP commit

- None needed — tree clean after PR 8 merge.
- Only untracked: `docs/mobile/designer-brief.pdf` (CSJ's own mobile-design work, NOT mine to commit).

## Open decisions

None blocking. The spec doc update for PRs 5a-5e/6/7/8 is the only outstanding paperwork item — next session can knock that out in ~5 min.

## Pick up from here (auto-continue contract)

1. **Update `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`** to reflect Pass 4 COMPLETE (currently shows IN FLIGHT 4/8). Sections to revise:
   - Frontmatter status: "Pass 4 (Properties) COMPLETE"
   - §0 sub-project status row
   - §15.3 pass order table — Pass 4 → DONE with merge SHA `c972fff` + PR list
   - §16.2 progress counts: 7 of 19 entity stores (was 6); 8 boundary tests passing (was 7) — `PropertyStoreBoundaryTest` LOCKED; 3 parity tests shipped (Savings + Pension + Property); 7 Store.md docs landed
   - §21.1 implementation rollout — Pass 4 row → DONE
   - §21.3 living progress log — add 2026-05-27 row for Pass 4 close-out
2. **CSJ to choose Pass 5 (Liabilities incl. mortgages) start** — no plan written yet. Decision needed: brainstorm via `superpowers:brainstorming` first or jump straight to plan-writing via `superpowers:writing-plans`?
3. **csjones re-deploy** — currently at `f2b5bec1`, dev now at `c972fff`. PR 6 has runtime code + migrations. Re-deploy needs: PHP-only rsync of source (no Vue changes since last deploy), then `git pull origin dev`, then **`php artisan migrate --force`** to apply PR 6's 2 migrations on csjones, then cache:clear + optimize. SSH passphrase requires CSJ.

## What the next Claude needs to know

- **`PropertyStore::forUser` is JOINT-AWARE.** This is the most important non-obvious fact about the store. If any future consumer uses `Property::where('user_id', $userId)` semantics, they MUST chain `->where('user_id', $user->id)` onto the Collection. Locked by `PropertyReadConsumerParityTest` (7 cases). Documented in `PropertyStore.md` quirk #6 + the "What the store does NOT expose" section.
- **`fromUpload` doesn't carry `joint_owner_name`** — quirk #11 in Store.md. Upload-created joint properties have `joint_owner_name = NULL` until user edits via form/Fyn. Pass 5 candidate to fix.
- **Sub-agent dispatch pattern works but Sonnet hits truncation** on multi-file edits where formatter races with import additions. Always instruct: "add import AND use it in the constructor in the SAME edit so the formatter sees it as used." If the agent truncates, `SendMessage` resume works for finishing — but be ready to take over manually if it stalls a second time.
- **2 pre-existing dev failures NOT caused by Pass 4**: `MobileScaffoldTest` + `Phase02ArchitectureTest` + `Phase03ArchitectureTest` (×2). Verified pre-existing via git stash + bare dev re-run during PR 8 review. Surface them only if they multiply.
- **Reviewer pattern locked in:** Sonnet implementer → 2 Opus reviewers (spec + code-quality) running in parallel via `feature-dev:code-reviewer` subagent type. Pattern documented for PR 8 in the dispatches.
- **`feature-dev:code-reviewer` agents have NO Bash tool** — they can't run tests. Code-quality agent has Bash. Don't ask the spec reviewer to run a suite.

## Branch / deploy state

- Branch: `dev`
- Behind origin: 0
- Ahead of origin: 0
- Tip: `c972fff` (Merge PR #402)
- Production (`main`): unchanged. Last release 22 May. Now ~50+ commits behind dev (entire Pass 4 sequence + parallel session work + SP3 mobile docs).
- csjones (dev staging): at `f2b5bec1` — needs re-deploy. PR 6 added 2 migrations not yet applied on csjones. **Run `php artisan migrate --force` after `git pull`.**
- PR 5/6/7/8 all green in CI implicitly (admin-merged from `dev` after local sweeps).

## SP1 Pass 4 close-out — acceptance criteria mapping (for vault sync next session-end)

| §16.1 gate | Closed by |
|---|---|
| 1. Single write path (Pest boundary) | PR 8 — `PropertyStoreBoundaryTest` LOCKED |
| 2. Three-ingest parity | PR 8 — `PropertyThreeIngestParityTest` (2 cases incl. `tenants_in_common`) |
| 3. Audit completeness — ingest_source | PR 1 (AuditLog::withContext wraps) + PR 8 — `PropertyAuditIngestSourceTest` (5 cases) |
| 4. Derived-column correctness | PR 6 — `PropertyDerivedColumnCalculator` + tests |
| 5. Snapshot policy applied | PR 6 — `propertyValue`/`propertyEquity` policies + tests |
| 6. Currency round-trip | n/a — Pass 4 GBP-only (deferred to a future sub-project pass; mirrors Pension) |
| 7. Tier-cap enforcement | PR 1 (seam) + PR 7 — `PropertyTierCapTest` (5 cases) |
| 8. Browser-tested via Playwright | csjones smoke after PR 8 merge — **outstanding**, requires csjones re-deploy first |

**Sub-Project 1 progress: 7 of 19 entity stores fully shipped** (Savings + R1-R4 ref-data + Pensions + Properties).
