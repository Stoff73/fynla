---
type: handover
mode: context-clear
date: 2026-05-27
session: 5
branch: dev
trigger: end-of-session (Pass 5 closed + Pass 6 plan written; auto-continue next session with Pass 6 PR 1 dispatch)
previous_session: 2026-05-27 session 4 (Pass 5 PRs 1-5a shipped)
---

# Context Clear Handover — 2026-05-27, Session 5

## Immediate state

**SP1 Pass 5 (Mortgages) FULLY CLOSED at PR 8 merge `e4d8039`. SP1 Pass 6 (Investments) plan WRITTEN and committed at `1b3a900`.** Working tree clean on `dev` at `1b3a900` (head after CSJTODO update commit). 8/19 SP1 stores shipped after Pass 5. Pass 6 ready to start executing PR 1 next session per CSJ instruction "write plan now, dispatch PR 1 next session".

## The thread

Session 5 closed Pass 5 + planned Pass 6:

- **Session-start** picked up from session-4 handover. Auto-continued with PR 5b dispatch per the handover's "Pick up from here".
- **Pass 5 PRs 5b → 5c → 5d → 5e** shipped via subagent-driven-development. Each PR went through 2-stage review (Opus spec + Opus code-quality), with fixup commits applied inline per reviewer findings:
  - **PR 5b** caught a unanimous CRITICAL regression: `mortgageStore->forUser($user)` filtered against itself collapsed the cross-link Holdings leg to empty in CrossModuleAssetAggregator. Fixup `6150d39` reverted to raw `Mortgage::whereIn` + added the 8th parity case locking the cross-link semantic. Merge `e653602`.
  - **PR 5c** had a spec-vs-code-quality reviewer disagreement on PersonalAccountsService HasMany sites. Resolved by Pass 4 PR 5d sibling precedent (PersonalAccountsService:101/229 stay un-migrated). Fixup `e6653ac` dropped 3 unused Mortgage imports + reordered constructor params. Merge `fc4fe51`.
  - **PR 5d** clean approval — only minor deferred dead-eager-load flagged at GoalsProjectionService:55. Merge `46dc4f2`.
  - **PR 5e** cleanest of the series — both reviewers APPROVE with zero findings. Merge `e50dfde`. Closes PR 5 fully.
- **PR 6 (cross-store recalc + 3 migrations)** the architecturally significant PR. Implementer hit MySQL 64-char index name limit (shortened to `mvs_mortgage_type_snapshotted_idx`) + had to broaden `PropertyStore::recalculateDerived` signature to accept nullable `$user`/`$source` for cross-store path. Both reviewers APPROVE; fixup `d258b10` dropped `final` modifier to match Pass 4 non-final sibling convention + added observer-dedup docblock. Merge `8ec33c6`.
- **PR 7 (tier-cap test)** small bounded PR. Plan §11 template had bugs (wrong exception class, non-existent Subscription::TIER_1, missing TierConfigurationStore::set method). Implementer correctly mirrored sibling pattern. Both reviewers APPROVE zero findings. Merge `ad5f777`.
- **PR 8 (lock-down + parity + audit + Store.md)** final PR of Pass 5. Boundary LOCKED with 3 allowlist entries (matching Pass 4 PropertyStore precedent including PreviewUserSeeder). 5 audit ingest tests + 2 three-ingest parity tests + 230-line MortgageStore.md. Fixup `bc360e9` added 6th audit-context-leak test (parity with Pass 4 sibling), populated migration history with accurate merge SHAs, extended ThreeIngestParityTest snapshot to include 3 derived columns. Merge `e4d8039`. **Pass 5 CLOSED.**
- **CSJ said "continue with canonical contracts"** → identified Pass 6 = Investments per spec §15.3. Audit revealed scope is much larger than Pass 5 (173 InvestmentAccount refs vs ~40 Mortgage; 6 models vs 1; polymorphic Holdings cross-module). Used `AskUserQuestion` for scope decision; CSJ chose **full Investment surface (all 6 models)** + **write plan now, dispatch PR 1 next session**.
- **Opus sub-agent for plan-writing hit a socket disconnect** after 56 tool uses / 12 minutes. Wrote the plan directly instead — 768 lines covering 16 PRs in template-pointer style ("follow Pass 5 PR N pattern" rather than re-listing identical steps). Plan commit `1b3a900`.

## Pass 5 final stats

| PR | # | Title | Merge SHA |
|----|---|-------|-----------|
| 1 | #403 | MortgageStore facade + boundary + normaliser + events | `fe5e1a1` |
| 2 | #404 | HTTP form requests | `a78ddd2` |
| 3 | #405 | Fyn AI write tools | `54f215b` |
| 4 | #406 | Upload + onboarding + seeders + service-internal | `1e39c45` |
| 5a | #407 | Estate/IHT reads + parity test | `49b0dd2` |
| 5b | #408 | NetWorth/Mobile/CrossModule (cross-link regression fix) | `e653602` |
| 5c | #409 | Coordination/AI/UserProfile | `fc4fe51` |
| 5d | #410 | Goals + Protection | `46dc4f2` |
| 5e | #411 | GDPR/Protection/RateAlerts (final read cluster) | `e50dfde` |
| 6 | #412 | Cross-store recalc + 3 migrations | `8ec33c6` |
| 7 | #413 | Tier-cap test | `ad5f777` |
| 8 | #414 | Lock-down + audit + parity + Store.md | `e4d8039` |

12 merges total (8 PRs but 5 of them are sub-clustered into 5a-5e). SP1 progress 7/19 → 8/19.

## Pass 6 plan summary

**Plan file**: `docs/superpowers/plans/2026-05-27-sub-project-1-pass-6-investments-plan.md` (768 lines, commit `1b3a900`).

**16 PRs planned** (12 numbered + 5 PR 5 sub-clusters):
1. InvestmentAccountStore facade + boundary + normaliser + events + tier-cap
2. HTTP form requests
3. Fyn AI write tools
4. Upload + onboarding + seeders + MigrateEstateToNetWorth audit
5a-5e. Read consumers (5 sub-clusters across 67+ consumer files)
6. HoldingStore cross-module facade + 2 listeners + `PensionStore::recalculateDerivedForPensionId`
7. Holding routing (closes Pass 3 DCPensionHoldingsController deferral)
8. InvestmentGoalStore + RiskProfileStore + InvestmentScenarioStore (3 satellite stores bundled)
9. RebalancingActionStore (single confirmed write site at RebalancingActionsController:57)
10. Derived columns + snapshots + cross-store recalc (5-6 migrations)
11. Tier-cap tests (×4 entities)
12. Lock-down + parity + audit + Store.md (×6) + PensionStore.md cleanup

**Unique architectural pieces (plan §0.2):**
- HoldingStore is **cross-module** — first such store in SP1. Accepts writes from BOTH InvestmentController AND DCPensionHoldingsController.
- 2 cross-store recalc listeners (Account ← Holdings AND Pension ← Holdings)
- 3 satellite stores in PR 8 bundle
- Observer entanglement (InvestmentAccountRiskObserver + InvestmentAccountGoalObserver)
- Polymorphic Holdings (`morphTo('holdable')` accepting InvestmentAccount OR DCPension)

## Files touched this session

```
app/Services/Mobile/MobileDashboardAggregator.php   (PR 5b — sumMortgageJointOwnerShares helper)
app/Services/Shared/CrossModuleAssetAggregator.php   (PR 5b — MortgageStore injection + cross-link fix)
app/Services/Coordination/HouseholdPlanningService.php  (PR 5c — 2 joint-aware migrations)
app/Services/AI/DuplicateAcknowledgement.php         (PR 5c — Collection-method hybrid)
app/Services/UserProfile/PersonalAccountsService.php (PR 5c — 1 joint-aware migration)
app/Services/UserProfile/LetterToSpouseService.php   (PR 5c — 1 primary-only migration)
app/Services/UserProfile/UserProfileService.php      (PR 5c — 1 primary-only migration)
app/Services/Goals/GoalsProjectionService.php        (PR 5d — 2 HasMany migrations)
app/Agents/ProtectionAgent.php                       (PR 5d — 1 HasMany migration)
app/Services/GDPR/DataExportService.php              (PR 5e — primary-only migration)
app/Services/Protection/ProtectionDataReadinessService.php  (PR 5e — optimization-preserving partial migration)
app/Console/Commands/SendMortgageRateAlerts.php      (PR 5e — system-scope KEEP with docblock)
app/Services/Stores/MortgageStore.php                (PR 6 — derived calc + snapshot policies + recalculateDerived hook)
app/Services/Stores/Recalc/MortgageDerivedColumnCalculator.php  (PR 6 NEW)
app/Services/Stores/PropertyStore.php                (PR 6 — recalculateDerivedForPropertyId + signature broaden + saveQuietly)
app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php  (PR 6 — canonical mortgage sum read)
app/Services/Stores/Snapshots/SnapshotPolicies.php   (PR 6 — mortgageBalance + mortgageRate policies)
app/Listeners/Mortgage/RecalculatePropertyOutstandingMortgage.php  (PR 6 NEW)
app/Models/MortgageValueSnapshot.php                 (PR 6 NEW)
app/Models/Mortgage.php                              (PR 6 — +6 fillable +6 casts +snapshots HasMany)
app/Models/Property.php                              (PR 6 — +1 fillable +1 cast for outstanding_mortgage_calculated_at)
app/Providers/EventServiceProvider.php               (PR 6 — 4 mappings appended)
app/Console/Commands/BackfillMortgageDerivedColumns.php       (PR 6 NEW)
app/Console/Commands/BackfillPropertyOutstandingMortgage.php  (PR 6 NEW)
database/migrations/2026_05_28_100000-100002_*.php   (PR 6 — 3 migrations)
database/factories/MortgageValueSnapshotFactory.php  (PR 6 NEW)
tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php  (PR 8 — LOCKED framing)
tests/Feature/Stores/MortgageAuditIngestSourceTest.php  (PR 8 NEW — 6 cases)
tests/Feature/Stores/MortgageThreeIngestParityTest.php  (PR 8 NEW — 2 cases incl. derived columns)
tests/Feature/Stores/MortgageReadConsumerParityTest.php (PR 5b — 8th case for cross-link)
tests/Feature/Stores/MortgageTierCapTest.php         (PR 7 NEW — 5 cases)
tests/Feature/Stores/MortgagePropertyReconciliationTest.php  (PR 6 NEW — 5 cases incl. loop prevention)
tests/Feature/Stores/MortgageDerivedColumnsBackfillTest.php  (PR 6 NEW — 2 cases)
tests/Unit/Listeners/Mortgage/RecalculatePropertyOutstandingMortgageTest.php  (PR 6 NEW — 5 cases)
tests/Unit/Services/Stores/Recalc/MortgageDerivedColumnCalculatorTest.php  (PR 6 NEW — 3 cases)
tests/Pest.php                                       (PR 6 — Unit/Listeners directory registered)
tests/Architecture/Phase02ArchitectureTest.php       (PR 5c — PersonalAccountsService 1→3 param count)
tests/Unit/Services/Mobile/MobileDashboardAggregatorTest.php  (PR 5b — MortgageStore constructor arg)
tests/Unit/Services/Coordination/HouseholdPlanningServiceTest.php  (PR 5c — MortgageStore constructor arg)
tests/Unit/Services/UserProfileServiceTest.php       (PR 5c)
tests/Unit/Services/UserProfile/FinancialCommitmentsTest.php  (PR 5c)
tests/Unit/Agents/ProtectionAgentTest.php            (PR 5d — Mockery mock)
tests/Unit/Agents/ProtectionAgentGoalsTest.php       (PR 5d — Mockery mock)
+ several existing Property tests modified in PR 6 to seed Mortgages via MortgageStore (per pre-Pass-5 contract update)
app/Services/Stores/MortgageStore.md                 (PR 8 NEW — 230 lines)
docs/superpowers/plans/2026-05-27-sub-project-1-pass-6-investments-plan.md  (session-end — Pass 6 plan)
CSJTODO.md                                           (updated 6 times this session)
```

## WIP commit

None — tree is clean. Last commit on dev: `1b3a900 plan(investments): SP1 Pass 6 plan — 16 PRs covering all 6 Investment models`. Untracked: `docs/mobile/designer-brief.pdf` (CSJ's own work, leave alone — same as session 3/4).

## Open decisions

None blocking. Pass 6 PR 1 dispatch is the next step. Plan §19 lists 4 open questions (tier-cap defaults, HoldingStore::forParent shape, HoldingStore location, currency round-trip) with proposed resolutions — implementer can resolve at PR 1 dispatch.

## Pick up from here (auto-continue contract)

1. **PR 1 dispatch — InvestmentAccountStore facade + boundary + normaliser + events + tier-cap.** Branch: `feat/investment-store-pr1` off `dev` at `1b3a900`. Follow plan §5 step-by-step (mirror Pass 5 PR 1 — `git show fe5e1a1`).

   Implementer brief should include:
   - The plan §5 full step list (1.1 through 1.9)
   - Sibling commit reference `fe5e1a1` for exact code shape
   - Pint stall lesson from prior passes (combine import + constructor reference in same edit)
   - The 4 open questions from plan §19 to resolve inline (Q1 tier-cap defaults, Q2 forParent shape — though Q2 doesn't apply to PR 1)
   - Audit verification step (file lists for §1.1 in the plan are approximate from `grep -rnE` counts; PR 1 implementer should verify exact write sites)

   Dispatch via Agent tool, subagent_type=general-purpose, model=sonnet, with foreground execution.

2. **After PR 1 merges**, dispatch PR 2 (HTTP form requests). Pattern: each PR ships as foreground implementer dispatch + 2 background reviewers in parallel + admin-merge after both APPROVE.

3. **Same review cycle as Pass 5** — Sonnet implementer → background-dispatched Opus spec reviewer + Opus code-quality reviewer → admin-merge after both APPROVE. Apply fixup commits inline if reviewers surface findings.

4. **Update CSJTODO after each PR merge** — tick off PR in §"Active track: SP1 Pass 6 (Investments)" + add detail line about merge SHA + reviewer outcomes (per Pass 5 convention).

## What the next Claude needs to know

- **Pass 6 is LARGER than Pass 5** — 16 PRs vs 8, 6 entities vs 1, 173+ InvestmentAccount refs vs ~40 Mortgage refs. Pace yourself.
- **HoldingStore is cross-module** — PR 6 + PR 7 require touching `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php` (closes Pass 3 deferral). Be careful not to break existing Pension tests when migrating Holdings.
- **Two cross-store recalc paths in PR 10** — `Holdings → InvestmentAccount.current_value_gbp` AND `Holdings → DCPension.current_value` (depending on `holdable_type`). Loop-prevention tests required for BOTH branches.
- **Observer entanglement** — InvestmentAccountRiskObserver + InvestmentAccountGoalObserver must keep firing. Store uses `->save()` (fires events) for user-driven writes; only cross-store recalc uses `saveQuietly` per Pass 5 PR 6 nuance.
- **Polymorphic Holdings** — `morphTo('holdable')`. Normaliser must validate `holdable_type` is `InvestmentAccount::class` or `DCPension::class` (no other parents allowed).
- **Plan template style differs from Pass 5** — Pass 6 plan uses template-pointer language ("follow Pass 5 PR N pattern") rather than re-listing identical steps. Cross-reference Pass 5 plan + Pass 5 merge commits for granular details when needed.
- **Subagent dispatch stalls** ~50% mid-Pint-diagnosis. SendMessage nudge resolves; after 2 stalls take over manually. Same pattern as Pass 5.
- **csjones is at `f2b5bec1`** — still hasn't been re-deployed. 5 migrations from Pass 4/5 accumulating + 5-6 more migrations coming in Pass 6 PR 10. Plan a deploy window after PR 12 (Pass 6 close-out) OR mid-Pass-6 (after PR 5e — InvestmentAccount routing complete).
- **InvestmentTransaction does NOT exist** — spec §15.3 reference was aspirational. Don't try to create a store for it.
- **RebalancingAction has writes** — confirmed at `RebalancingActionsController:57`. Ships as a full store in PR 9, NOT deferred.
- **Pass 5 plan template bugs to AVOID in Pass 6 implementation**:
  - Wrong exception class (`TierCapExceededException` vs actual `TierLimitExceededException`)
  - `Subscription::TIER_1` (tier is a User column)
  - Global function helpers in tests (use inline canonical arrays)
  - MySQL index name 64-char limit
  - Verify store update signature (`int $id` vs Model instance)
- **PensionStore.md needs updating in PR 12** — remove the deferred-debt note at `:40` about DCPensionHoldingsController direct queries.

## Branch / deploy state

- **Branch:** `dev`
- **Behind origin:** 0
- **Ahead of origin:** 0
- **Tip:** `1b3a900` (`plan(investments): SP1 Pass 6 plan — 16 PRs covering all 6 Investment models`)
- **Production (`main`):** unchanged. Last release 22 May. Now ~75+ commits behind dev (Pass 4 + Pass 5 + Pass 6 plan).
- **csjones (dev staging):** at `f2b5bec1` — still needs Pass 4 PR 6 (2 migs) + Pass 5 PR 6 (3 migs) deployed = 5 pending. Eventually + Pass 6 PR 10 (5-6 migs) = 10-11 pending if csjones not re-deployed before then.

## Pass 6 progress tracker (initial — to be ticked off as PRs ship)

| PR | Title | Branch | Status |
|----|-------|--------|--------|
| 1 | InvestmentAccountStore facade + boundary + normaliser + events + tier-cap | `feat/investment-store-pr1` | NEXT |
| 2 | HTTP form requests | `feat/investment-store-pr2` | queued |
| 3 | Fyn AI write tools | `feat/investment-store-pr3` | queued |
| 4 | Upload + onboarding + seeders + MigrateEstateToNetWorth | `feat/investment-store-pr4` | queued |
| 5a | Analytics/AssetLocation/Fees + parity test | `feat/investment-store-pr5a` | queued |
| 5b | Goals/ModelPortfolio/Performance | `feat/investment-store-pr5b` | queued |
| 5c | Rebalancing/Recommendation/Tax | `feat/investment-store-pr5c` | queued |
| 5d | Utilities + Agents | `feat/investment-store-pr5d` | queued |
| 5e | Cross-module reads | `feat/investment-store-pr5e` | queued |
| 6 | HoldingStore cross-module + listeners + PensionStore.recalculate | `feat/investment-store-pr6` | queued |
| 7 | Holding routing (closes Pass 3 deferral) | `feat/investment-store-pr7` | queued |
| 8 | Goal + Risk + Scenario satellite stores | `feat/investment-store-pr8` | queued |
| 9 | RebalancingActionStore | `feat/investment-store-pr9` | queued |
| 10 | Derived columns + snapshots + cross-store recalc | `feat/investment-store-pr10` | queued |
| 11 | Tier-cap tests | `feat/investment-store-pr11` | queued |
| 12 | Lock-down + parity + audit + Store.md | `feat/investment-store-pr12` | queued |

**Sub-Project 1 progress when Pass 6 closes:** 14 of 19 entity stores fully shipped.
