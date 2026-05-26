# CSJTODO — Fynla

*Last updated: 2026-05-26 — end-of-day wrap, session 4 — Pass 4 Properties at 3/8 PRs merged; subagent-driven-development workflow proven through PRs 1–3; new CSJTODO track for the Pass 4 + remaining-SP1 work*

---

## Active track: SP1 Pass 4 (Properties)

**Plan:** `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md`
**Execution pattern:** subagent-driven-development — implementer (Sonnet) → spec reviewer (Opus) → code-quality reviewer (Opus) → CSJ admin-merge per PR
**Branch convention:** `feat/property-store-prN` off `dev`

### PRs merged
- [x] **PR #387** — Pass 4 PR 1: PropertyStore facade + arch boundary + normaliser + 4 events (merge `9da1590`)
- [x] **PR #388** — Pass 4 PR 2: HTTP form requests + cross-store tier-limit Option A alignment (merge `b8cbec5`)
- [x] **PR #389** — Pass 4 PR 3: Fyn AI write tools + DB::transaction atomicity (merge `ba42683`)

### PRs remaining (in order)
- [ ] **PR 4** — Point upload + onboarding + seeders at PropertyStore. Plan §8. Routes DocumentProcessor, OnboardingService, AssetCaptureEntityExtractor, PreviewUserSeeder, ChrisUserSeeder, LifecycleTestSeeder, MigrateEstateToNetWorth. New PropertyUploadIngestTest. **Pre-emptive TierConfigurationSeeder audit on affected test files BEFORE dispatch.**
- [ ] **PR 5** — Point read consumers at PropertyStore (sub-clustered). Plan §9. ~21 service files. Likely sub-cluster: 5a Estate/IHT, 5b NetWorth/Mobile, 5c Coordination/Trust, 5d AI/Profile, 5e Tax/Documents. Biggest PR of Pass 4.
- [ ] **PR 6** — Canonical derived columns + snapshot table. Plan §10. `current_value_gbp`, `equity_gbp`, `loan_to_value_pct` + PropertyValueSnapshot table + PropertyDerivedColumnCalculator + BackfillPropertyDerivedColumns command + 2 snapshot policies.
- [ ] **PR 7** — Tier-cap test for property. Plan §11. PropertyTierCapTest with 5 cases. Enforcement seam already wired in PR 1.
- [ ] **PR 8** — Lock-down + parity + audit + Store.md. Plan §12. Reword boundary to LOCKED framing, PropertyAuditIngestSourceTest, PropertyThreeIngestParityTest (incl. tenants_in_common case), PropertyStore.md. §16 close-out IN-LINE.

### Deploy gate
- [ ] **csjones deploy** before PR 4 starts — 6 commits behind dev (PR 3 + CoALA docs). Use `./deploy/csjones-fynla/build.sh` + rsync + `git pull origin dev` on csjones. PR 3 has runtime code (CoordinatingAgent + PropertyNormaliser).

---

## Sub-Project 1 overall — 6 of 19 entity stores shipped

| Pass | Entity | Status |
|---|---|---|
| 1 | Savings | DONE (locked PR 8) |
| 2 | Reference data R1-R4 | DONE (locked 26 PRs) |
| 3 | Pensions (DC/DB/State/InputHistory) | DONE (8 PRs + close-out PR #385) |
| **4** | **Properties** | **3/8 PRs (this track)** |
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

---

## Known issues

- None blocking. Pass 4 PR 4 can start immediately after csjones deploy.

---

## Deploy status

- **main (fynla.org):** unchanged. Last release 22 May. ~30 commits behind dev now (Pass 4 PRs 1+2+3 + CoALA docs + Pass 3 close-out + Pass 4 plan).
- **dev (csjones.co/fynla):** at `b8cbec5` (Pass 4 PR 2 boundary). 6 commits behind dev HEAD. Deploy gate before PR 4 starts.

---

## Reminders for next session

- Read both today's handovers:
  - `May/May26Updates/handover-2026-05-26-session-4-clear.md` — full session 4 detail
  - `May/May27Updates/handover-2026-05-27-session-2.md` — this end-of-day
- Plan §8 of `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md` is the canonical spec for PR 4.
- Subagent-driven-development workflow continues. TaskList persists PR 4–8 staged.
- Option A tier-limit response shape locked across PropertyController + RetirementController + SavingsController.
- Pre-emptive `TierConfigurationSeeder` audit on all test files PR 4 touches — same Critical-issue class as PR 3 will surface otherwise.
- Don't `migrate:fresh`. Don't ship to main without csjones browser-verify first.
