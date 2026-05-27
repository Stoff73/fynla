# CSJTODO — Fynla

*Last updated: 2026-05-27 — session 3 — Pass 4 Properties at 4/8 PRs merged (PR #390 merged at `df357e9`); csjones deployed to `aa65ab80` at start of session*

---

## Active track: SP1 Pass 4 (Properties)

**Plan:** `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md`
**Execution pattern:** subagent-driven-development — implementer (Sonnet) → spec reviewer (Opus) → code-quality reviewer (Opus) → CSJ admin-merge per PR
**Branch convention:** `feat/property-store-prN` off `dev`

### PRs merged
- [x] **PR #387** — Pass 4 PR 1: PropertyStore facade + arch boundary + normaliser + 4 events (merge `9da1590`)
- [x] **PR #388** — Pass 4 PR 2: HTTP form requests + cross-store tier-limit Option A alignment (merge `b8cbec5`)
- [x] **PR #389** — Pass 4 PR 3: Fyn AI write tools + DB::transaction atomicity (merge `ba42683`)
- [x] **PR #390** — Pass 4 PR 4: upload + onboarding + seeders at PropertyStore (merge `df357e9`). Surfaced + disclosed 2 pre-existing bug fixes (MigrateEstateToNetWorth current_valuation→current_value; OnboardingService annual_rental_income drop). In-flight Minor #1 fix added PropertyNormaliser::fromForm seam in OnboardingService (commit `3074029`).
- [x] **PR #395** — Pass 4 PR 5a: Estate/IHT read consumers + PropertyReadConsumerParityTest (merge `262ad96`). Code-quality review caught a Major regression — `PropertyStore::forUser` is JOINT-AWARE (returns `user_id = ? OR joint_owner_id = ?`), silently broadening 7 sites that originally used `Property::where('user_id', $userId)`. Fix appends `->where('user_id', $user->id)` to the Collection chain for primary-only consumers. 7-case parity test locks the contract for 5b/5c/5d/5e.

### PRs remaining (in order)
- [ ] **PR 5** — Point read consumers at PropertyStore (sub-clustered). Plan §9. **5a DONE.** Remaining: 5b NetWorth/Mobile (NetWorthService + MobileDashboardAggregator + CrossModuleAssetAggregator), 5c Coordination/Trust (HouseholdPlanningService + TrustAssetAggregatorService), 5d AI/Profile (AdvicePromptBuilder + DuplicateAcknowledgement + PersonalAccountsService + UserProfileService + ProfileCompletenessChecker), 5e Tax/Documents (IncomeDefinitionsService + DocumentTypeDetector + FieldMappers\PropertyMapper).

### ⚠️ CRITICAL — PropertyStore::forUser is joint-aware (5a review-loop discovery)

`PropertyStore::forUser(User $user): Collection` calls `Property::forUserOrJoint($user->id)->get()` internally — returns `WHERE user_id = ? OR joint_owner_id = ?`. Same applies to `forUserByType`.

**For any consumer that originally used `Property::where('user_id', $userId)` (primary-only), chain `->where('user_id', $user->id)` onto the Collection to restore primary-only semantics.** Pattern:

```php
// Pre-PR-5a: Property::where('user_id', $userId)->sum('current_value')
// Post: $propertyStore->forUser($user)->where('user_id', $user->id)->sum('current_value')
```

For consumers that originally used `Property::forUserOrJoint($userId)` (joint-aware, typically followed by `calculateUserShare`), use `forUserWithJointOwner($user)` and DO NOT add the filter.

`PropertyReadConsumerParityTest` locks 7 cases covering both patterns.
- [ ] **PR 6** — Canonical derived columns + snapshot table. Plan §10. `current_value_gbp`, `equity_gbp`, `loan_to_value_pct` + PropertyValueSnapshot table + PropertyDerivedColumnCalculator + BackfillPropertyDerivedColumns command + 2 snapshot policies.
- [ ] **PR 7** — Tier-cap test for property. Plan §11. PropertyTierCapTest with 5 cases. Enforcement seam already wired in PR 1.
- [ ] **PR 8** — Lock-down + parity + audit + Store.md. Plan §12. Reword boundary to LOCKED framing, PropertyAuditIngestSourceTest, PropertyThreeIngestParityTest (incl. tenants_in_common case), PropertyStore.md. §16 close-out IN-LINE.

### Deploy gate
- [x] **csjones deploy** — completed at start of 2026-05-27 session 3. csjones at `aa65ab80` (matches dev pre-PR4-merge). Bundle hash verified byte-identical. Re-deploy before PR 5 dispatch (PR 4 added runtime code to OnboardingService + DocumentProcessor + MigrateEstateToNetWorth).
- [ ] **csjones re-deploy** before PR 5 starts — at minimum 2 commits behind (PR #390 merge `df357e9` + #391 mobile-landing merge `68783e3`).

---

## Sub-Project 1 overall — 6 of 19 entity stores shipped

| Pass | Entity | Status |
|---|---|---|
| 1 | Savings | DONE (locked PR 8) |
| 2 | Reference data R1-R4 | DONE (locked 26 PRs) |
| 3 | Pensions (DC/DB/State/InputHistory) | DONE (8 PRs + close-out PR #385) |
| **4** | **Properties** | **4/8 PRs (this track)** |
| 5 | Liabilities (incl. mortgages) | not started — no plan |
| 6 | Investments | not started — no plan |
| 7 | Income + Expenditure | not started — no plan |
| 8 | Protection | not started — no plan |
| 9 | Family members | not started — no plan |
| 10 | Goals + life events | not started — no plan |
| 11 | Chattels | not started — no plan |
| 12 | Business interests | not started — no plan |
| 13 | Trusts | not started — no plan |
| 14 | Wills + LPAs | not started — no plan |

---

## Parallel: CoALA track

CSJ shipped `fynla-coala-implementation-plan.md`, `fynla-coala-stakeholder-brief.md`, and 6 phase PRDs (`May/May27Updates/PRD-coala-phase-{1-6}-*.md`) to dev. Separate workstream from SP1 store migration. Not in this CSJTODO's scope — handled by CSJ directly.

---

## Tech debt deferred (from PR 1–3 review loop)

- [ ] **`validateCanonical($data, $partial)` vestigial parameter** — exists on SavingsStore + PensionStore validateDcCanonical. PropertyStore had it removed in PR 2 review. Either align siblings or document the reason it's kept.
- [ ] **Test file location convention drift** — Property HTTP integration test at `tests/Feature/Stores/PropertyHttpIntegrationTest.php`; Pension's at `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php`. Pick one for future passes (5+).
- [ ] **`CreateInvestmentAccountTest` failures in broad sweeps** — 2 cases (validation_failed + preview-blocks) fail in `pest tests/Feature/Api/ tests/Unit/Services/Stores/ tests/Architecture/ tests/Feature/Stores/ tests/Feature/AI/DirectWrite/` but pass in isolation. Test-ordering / DB state interference. NOT caused by Pass 4 — pre-existing. Investigate when convenient.
- [ ] **PropertyController has 5 deps by end of Pass 5** — flag for Pass 5 reviewer whether MortgageService should fold into MortgageStore at that point.

## Tech debt deferred (from PR 4 review loop)

- [ ] **Constructor injection for stores + normalisers in OnboardingService** — `OnboardingService` resolves `PropertyStore`, `SavingsStore`, `PensionStore`, `PropertyNormaliser`, `SavingsAccountNormaliser`, `PensionNormaliser` via `app(...)` instead of `private readonly` constructor DI per `app/Services/CLAUDE.md`. Pre-existing pattern (not a PR 4 regression). Address as part of PR 5/6 read-consumer cleanup or a standalone follow-up.
- [ ] **`ChrisUserSeeder` BTL property `ownership_type=joint` without `joint_owner_id`** — `database/seeders/ChrisUserSeeder.php:159-177` has `'ownership_type' => 'joint'` + `'joint_owner_name' => 'wife'` but no `joint_owner_id` (chris is `marital_status=single`). `validateCanonical` accepts this, but the canonical Joint Assets pattern (CLAUDE.md Rule #7) uses `joint_owner_id` + `ownership_percentage`. Pre-existing — flagged for visibility during a future seeder canonicalisation pass.
- [ ] **`PropertyUploadIngestTest` could lock `IngestSource::UPLOAD` audit signal** — `tests/Feature/Stores/PropertyUploadIngestTest.php` asserts row count + 2 field values but not the audit-context `ingest_source` value. Optional consistency hardening — add `AuditLog::where('ingest_source','upload')->exists()` assertion. Pass 3 PensionStore equivalent test was happy-path-only too; consistent. Hardening, not a defect.
- [ ] **Out-of-scope: `MigrateEstateToNetWorth` `current_valuation`→`current_value` bug pattern exists in sibling methods** — `migrateBusiness` (:201) and `migrateChattel` (:223) still pass `current_valuation` for `business_interests` and `chattels` tables. PR 4 only fixed the Property case. Same fix needed in Pass 11 (Chattels) and Pass 12 (Business interests) when those passes start.

---

## Known issues

- None blocking. Pass 4 PR 4 can start immediately after csjones deploy.

---

## Deploy status

- **main (fynla.org):** unchanged. Last release 22 May. ~35 commits behind dev now (Pass 4 PRs 1+2+3+4 + CoALA docs + Pass 3 close-out + Pass 4 plan + mobile-landing).
- **dev (csjones.co/fynla):** at `aa65ab80` (just before PR 4 merge). 2+ commits behind dev HEAD (PR #390 + PR #391 mobile-landing merged after deploy). Re-deploy before PR 5 dispatch.

---

## Reminders for next session

- PR 4 merged at `df357e9`. csjones still at `aa65ab80` — re-deploy before PR 5 dispatch.
- Plan §9 of `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md` is the canonical spec for PR 5 (read consumers, sub-clustered, ~21 files — biggest PR of Pass 4).
- Sub-cluster strategy from Pass 3 precedent: 5a Estate/IHT → 5b NetWorth/Mobile → 5c Coordination/Trust → 5d AI/Profile → 5e Tax/Documents. One PR per cluster OR bundle in one branch with multi-commit per cluster — CSJ's call at dispatch time.
- Subagent-driven-development workflow continues: implementer (Sonnet) → spec reviewer (Opus) → code-quality reviewer (Opus) → CSJ admin-merge per cluster PR.
- PR 4 review-loop lessons (for PR 5 implementer brief):
  - **Don't skip the normaliser seam** — every store call should be `Normaliser::from*` + `Store::create/update`. PR 4 code-quality review caught this on OnboardingService's property block.
  - **Disclose pre-existing bugs in PR body** rather than silently fixing — PR 4 did this for `current_valuation` and `annual_rental_income`.
  - **TierConfigurationSeeder discipline** — every test exercising `*Store::create` must seed it in `beforeEach`.
- Don't `migrate:fresh`. Don't ship to main without csjones browser-verify first.
