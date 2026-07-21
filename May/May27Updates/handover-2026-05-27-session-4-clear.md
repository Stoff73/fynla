---
type: handover
mode: context-clear
date: 2026-05-27
session: 4
branch: dev
trigger: context-handover skill (tripwire fired at ~707k tokens / 87.5% of CSJ's 800k Fynla budget)
previous_session: 2026-05-27 session 3 (context-handover; Pass 4 Properties COMPLETE)
---

# Context Clear Handover — 2026-05-27, Session 4

## Immediate state

**SP1 Pass 5 (Mortgages) is 5/9 PRs merged. Just shipped PR 5a (Estate/IHT read consumers + MortgageReadConsumerParityTest) at merge `49b0dd2`.** Working tree clean on `dev` at `034f410`. Pass 5 plan written + 4 foundational PRs (1-4) + first of 5 read-consumer sub-clusters (5a) all in dev. **4 more PRs remaining**: PR 5b, 5c, 5d, 5e (read-consumer sub-clusters) → PR 6 (cross-store recalc + 3 migrations) → PR 7 (tier-cap test) → PR 8 (lock-down + parity + audit + Store.md).

## The thread

Session 4 ran the full subagent-driven-development cycle for SP1 Pass 5:

- **Session-start** picked up from session-3 handover. Auto-continued with the spec-doc update for Pass 4 close-out (commit `eb260fc`) per the handover's "Pick up from here".
- **CSJ chose Pass 5 entry: "continue with the canonical contracts, as before"** — interpreted as: write the plan, then execute subagent-driven. Scope decision (logged in plan §0.1): **Pass 5 = Mortgages only**, defer `App\Models\Estate\Liability` (unsecured consumer debt) to a future Pass 5b. Rationale: architecturally separate (different namespace, controller, Vue surface; no shared store), and the natural reconciliation is with Pass 4's `properties.outstanding_mortgage` denormalised cache.
- **Plan written** at `docs/superpowers/plans/2026-05-27-sub-project-1-pass-5-mortgages-plan.md` (3216 lines, 8 PRs, 68 step-level checkboxes). Commit `93d3523`.
- **Unique-to-Pass-5 architectural piece (PR 6)**: cross-store recalc. A write to MortgageStore for `property_id=X` triggers `PropertyStore::recalculateDerivedForPropertyId(X)` synchronously via an event listener. PropertyDerivedColumnCalculator switches from reading denormalised `properties.outstanding_mortgage` to reading the canonical mortgages sum. One-way recalc (Mortgage → Property only).
- **CSJ chose: "1" (Subagent-Driven)** for execution.
- **5 PRs shipped in order via subagent-driven-development**: PR #403 (PR 1 — facade + boundary + events + tier-cap) → PR #404 (PR 2 — HTTP form requests) → PR #405 (PR 3 — Fyn AI write tools) → PR #406 (PR 4 — upload + onboarding + seeders + service-internal) → PR #407 (PR 5a — Estate/IHT reads + parity test). Every PR went through 2-stage review (spec + code-quality, both Opus). Every PR caught Important issues fixed inline before admin-merge.

**Review-loop lessons (live across sub-agent dispatches):**
- **Pint formatter strips unused imports** — this gotcha bit PR 1, PR 2, PR 3, PR 4, and now PR 5a (5 of 6 files). Mitigation: add the import AND reference it in the SAME edit so Pint sees it as used. If the formatter strips on the first pass, re-add the import; the constructor reference preserves it on the second pass. PR 5a implementer documented this in their report.
- **Sibling-convention drift caught at code-quality stage, not spec stage.** PR 1's first implementer used `final class`, `Dispatchable` events, `Mortgage $entity` taking instance for update/delete. Code-quality reviewer caught all 4 vs the Pass 4 PropertyStore template. Fixed in PR 1 commit `4eeb4cb`.
- **`TierLimitExceededException` must be caught at every external HTTP/Fyn entry point** — PR 2 missed it in MortgageController::store (would surface as 500 not 403), PR 3 wired it correctly in CoordinatingAgent::handleCreateMortgage.
- **PropertyController was missing `StoreValidationException` import** — pre-existing Pass 4 PR 2 bug. Catch blocks at lines 155, 289 would silently not match. Fixed in PR 4 review-fix commit `72c7b76`.
- **Sub-agent dispatch stalls happen ~50% of the time** — PR 2, PR 4, PR 5a all needed a SendMessage nudge to continue past mid-task Pint diagnoses. Pattern: the implementer pauses to explain the formatter issue rather than just applying the fix. SendMessage nudge with "do not stop again, just complete the work" reliably resumes them.

## Files touched this session

```
docs/superpowers/specs/2026-05-14-module-canonical-store-design.md  (Pass 4 close-out spec update)
docs/superpowers/plans/2026-05-27-sub-project-1-pass-5-mortgages-plan.md  (NEW — 3216 lines)
app/Services/Stores/MortgageStore.php  (NEW — PR 1)
app/Services/Stores/Normalisers/MortgageNormaliser.php  (NEW — PR 1)
app/Events/Mortgage/Mortgage{Created,Updated,Deleted,Restored}.php  (NEW — PR 1)
database/seeders/TierConfigurationSeeder.php  (PR 1 — added 'mortgage' key)
app/Http/Controllers/Api/MortgageController.php  (PR 2)
app/Http/Controllers/Api/PreviewController.php  (PR 2)
app/Agents/CoordinatingAgent.php  (PR 3 + PR 4 — handleCreateMortgage + handleCreateProperty residual)
app/Services/Property/MortgageService.php  (PR 4)
app/Services/Onboarding/OnboardingService.php  (PR 4)
app/Http/Controllers/Api/PropertyController.php  (PR 4 — cascade-delete atomicity + StoreValidationException import)
database/seeders/PreviewUserSeeder.php  (PR 4 — mortgage seed via store::updateOrCreate)
database/seeders/ChrisUserSeeder.php  (PR 4 — same)
app/Services/Estate/{EstateAssetAggregatorService,EstateActionDefinitionService,EstateDataReadinessService,IHTFormattingService,LetterEstateValidationService,ComprehensiveEstatePlanService}.php  (PR 5a — 6 Estate services)
tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php  (NEW PR 1, trimmed PR 2/3/4, allowlist now 3 entries)
tests/Unit/Services/Stores/MortgageStoreTest.php  (NEW PR 1)
tests/Unit/Services/Stores/MortgageStoreEventsTest.php  (NEW PR 1)
tests/Unit/Services/Stores/Normalisers/MortgageNormaliserTest.php  (NEW PR 1)
tests/Feature/Stores/MortgageHttpIntegrationTest.php  (NEW PR 2)
tests/Feature/Stores/MortgageFynCaptureIntegrationTest.php  (NEW PR 3)
tests/Feature/Stores/MortgageUploadIngestTest.php  (NEW PR 4)
tests/Feature/Stores/MortgageReadConsumerParityTest.php  (NEW PR 5a — 7 cases locking joint-aware vs primary-only contract)
tests/Unit/Services/MortgageServiceTest.php  (PR 4 follow-up — DI fix)
CSJTODO.md  (updated 5 times — once per PR cluster)
```

## WIP commit

None — tree is clean. Last commit on dev: `034f410 docs(csjtodo): PR 5a merged — log sub-cluster progress + 5b–5e queue`. Untracked: `docs/mobile/designer-brief.pdf` (CSJ's own mobile-design work — leave alone, same as session 3).

## Open decisions

None blocking. Pass 5 PRs 5b through 8 follow the established pattern from PR 1-5a. CSJ has been autonomous on every PR — admin-merge per the established solo-reviewer pattern.

## Pick up from here (auto-continue contract)

1. **PR 5b — NetWorth/Mobile/CrossModule read consumers.** Sites:
   - `app/Services/NetWorth/NetWorthService.php`
   - `app/Services/Mobile/MobileDashboardAggregator.php` — may need a `sumMortgageJointOwnerShares` helper mirroring the Pass 4 savings/property `sumPropertyJointOwnerShares` precedent
   - `app/Services/Shared/CrossModuleAssetAggregator.php`

   Read patterns: confirm with `grep -nE "Mortgage::|->mortgages\(\)" <files>`. Joint-aware vs primary-only matters per parity test contract (see `tests/Feature/Stores/MortgageReadConsumerParityTest.php` cases — pattern shipped in PR 5a).

   Branch: `feat/mortgage-store-pr5b` off `dev` at `034f410`.

   Dispatch pattern: same as PR 5a — sonnet implementer with full plan §9.2 + sibling lessons inline, then opus spec + code-quality reviewers. **Forewarn the implementer about Pint stripping `MortgageStore` imports**; tell them to expect to re-add after first formatter pass.

2. **PR 5c** — Coordination + AI + UserProfile reads (HouseholdPlanningService, AdvicePromptBuilder, DuplicateAcknowledgement, LetterToSpouseService, PersonalAccountsService, UserProfileService).

3. **PR 5d** — Goals + Plans + Investment + agents (GoalsProjectionService, LifeEventService, SavingsPlanService, InvestmentPlanService, UserContextBuilder, TaxDragCalculator, GoalsAgent, RetirementAgent, SavingsAgent, ProtectionAgent).

4. **PR 5e** — Property-internal + DataExport + Protection (PropertyService, PropertyCalculationService, MortgageService own calc helpers, DataExportService, ProtectionDataReadinessService, SendMortgageRateAlerts).

5. **PR 6** — Architecturally significant. Cross-store recalc (Mortgage → Property reconciliation). 3 migrations: derived columns on mortgages, MortgageValueSnapshot table, outstanding_mortgage_calculated_at on properties. New MortgageDerivedColumnCalculator. New `RecalculatePropertyOutstandingMortgage` listener. Update PropertyDerivedColumnCalculator to read canonical mortgages sum. 2 backfill commands. **`MortgagePropertyReconciliationTest`** is the load-bearing integration test. Plan §10 covers in full detail.

6. **PR 7** — `MortgageTierCapTest` (5 cases, mirrors PropertyTierCapTest precedent).

7. **PR 8** — Boundary LOCKED, MortgageAuditIngestSourceTest, MortgageThreeIngestParityTest (includes tenants_in_common coercion case), `MortgageStore.md` (~200 lines / 11 quirks).

After PR 8: SP1 progress = 8 of 19 entity stores fully shipped.

## What the next Claude needs to know

- **`MortgageStore::forUser` is joint-aware** (returns `user_id = ? OR joint_owner_id = ?`). For primary-only semantics, use `forUserPrimaryOnly`. This is the SAME regression class Pass 4 PR 5a surfaced for PropertyStore — `MortgageReadConsumerParityTest` (PR 5a, 7 cases) locks the contract. Every sub-cluster 5b-5e must consume the parity contract.

- **`tenants_in_common` is NOT a valid Mortgage ownership_type.** MortgageNormaliser coerces TIC → joint at the boundary; MortgageStore::validateCanonical rejects TIC strictly at the store layer (defence in depth for callers that bypass the normaliser). Pass 4's `properties.ownership_type='tenants_in_common'` rows still have valid mortgages with `ownership_type='joint'`.

- **`User->mortgages()` HasMany is primary-only** (`user_id = ?`). Any consumer using `$user->mortgages()->...` was getting primary-only semantics — when migrating, use `forUserPrimaryOnly` to preserve.

- **Pint formatter quirk**: when adding a `use App\Services\Stores\MortgageStore;` import, the formatter strips it on first pass if the constructor reference isn't ALREADY present. Workaround: add the import + use the symbol in the constructor signature in the SAME edit. If it gets stripped anyway (PR 5a hit this 5/6 files), re-add the import after the constructor reference is in place — the formatter then preserves it.

- **Sub-agent dispatch stall pattern**: ~50% of dispatches pause mid-Pint-diagnosis. Send a SendMessage with "do not stop again, just complete the work" — they resume reliably. After 2 stalls, take over manually (per CLAUDE.md sub-agent feedback rules).

- **csjones is at `f2b5bec1`** — Pass 4 PR 6's 2 migrations + all of Pass 5 (PR 1-5a) NOT yet deployed. Pass 5 PR 1 added zero migrations; PR 6 will add 3 migrations. csjones re-deploy needed before any Playwright smoke (Pass 4 §16.1 gate 8 still outstanding, now Pass 5 gate 8 will join the list).

- **PreviewUserSeeder is in the boundary allowlist** (PR 4 fix-commit `72c7b76`) — the `deleteUserData` pre-seed bulk-cleanup uses `Mortgage::where(...)->delete()` which is a query-builder chain that the boundary regex doesn't catch. Allowlisted with permanent PR 8 LOCKED rationale.

- **`MortgageStore::create` throws `StoreValidationException` AND `TierLimitExceededException`** — every external entry point (controller, agent, service caller) MUST catch both. Pass 4 sibling pattern in PropertyController.

- **Pass 5 plan §9 has the canonical sub-cluster file lists**. Each sub-cluster mirrors Pass 4 PR 5a/5b/5c/5d/5e structure.

## Branch / deploy state

- **Branch:** `dev`
- **Behind origin:** 0
- **Ahead of origin:** 0
- **Tip:** `034f410` (`docs(csjtodo): PR 5a merged — log sub-cluster progress + 5b–5e queue`)
- **Production (`main`):** unchanged. Last release 22 May. Now ~60+ commits behind dev (entire Pass 4 sequence + all of Pass 5 so far + SP3 mobile docs).
- **csjones (dev staging):** at `f2b5bec1` — needs re-deploy. Pass 4 PR 6 added 2 migrations; Pass 5 has added 0 migrations to date (PR 6 will add 3). Run `git pull origin dev && php artisan migrate --force && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && composer dump-autoload -o && php artisan optimize` on next deploy.

## Pass 5 progress tracker

| PR | Title | Merge SHA | Status |
|----|-------|-----------|--------|
| #403 | PR 1 — MortgageStore facade + boundary + normaliser + events + tier-cap | `fe5e1a1` | DONE |
| #404 | PR 2 — HTTP form requests through MortgageStore | `a78ddd2` | DONE |
| #405 | PR 3 — Fyn AI write tools through MortgageStore | `54f215b` | DONE |
| #406 | PR 4 — Upload + onboarding + seeders + service-internal | `1e39c45` | DONE |
| #407 | PR 5a — Estate/IHT reads + MortgageReadConsumerParityTest | `49b0dd2` | DONE |
| — | PR 5b — NetWorth/Mobile/CrossModule reads | — | NEXT |
| — | PR 5c — Coordination/AI/UserProfile reads | — | queued |
| — | PR 5d — Goals/Plans/Investment + agents | — | queued |
| — | PR 5e — Property-internal/DataExport/Protection | — | queued |
| — | PR 6 — Derived columns + snapshots + cross-store recalc (3 migrations) | — | queued |
| — | PR 7 — Tier-cap test | — | queued |
| — | PR 8 — Lock-down + parity + audit + Store.md | — | queued |

**Sub-Project 1 progress when Pass 5 closes:** 8 of 19 entity stores fully shipped (Savings + 4 ref-data + Pensions + Properties + Mortgages).
