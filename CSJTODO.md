# CSJTODO — Fynla

*Last updated: 26 March 2026 — sessions 11 & 12*

---

## Session 11 (26 March) — Pension Detail View: Holdings Tab + Fee Display + OCF Input

### Completed
- [x] Pension detail view: Holdings moved to dedicated tab (Overview → Holdings → Projections → Documents)
- [x] Holdings tab: table with fund name, type, allocation %, value, OCF, cash remainder, fee summary bar
- [x] Fees section: platform fee handles fixed/percentage types with frequency display
- [x] Fees section: advisor fee row added (shown when > 0)
- [x] Fees section: total annual cost includes platform + advisor + weighted OCF
- [x] InlineHoldingsEditor: OCF % column added (captures OCF on holding creation)
- [x] Fixed CSS conflicts: font-medium/font-semibold, purple-600 → violet-600
- [x] Browser tested: 3 pensions (SIPP, Occupational, Stakeholder) with holdings + OCF + fees
- [x] Edit tested: modified SIPP fund value and contribution, verified save
- [x] Merged to main (PR #162)

## Session 12 (26 March) — Fee System Gaps: Actions, Projections, Premiums

### Completed
- [x] Fee system map: comprehensive trace across Investment, Pension, Protection (feesMap.md)
- [x] 3 pension fee action triggers: high total (>1.0%), high platform (>0.8%), high fund OCF (>0.5%)
- [x] PensionPortfolioAnalyzer: handles fixed fee types + advisor fee in total calculation
- [x] PensionProjector + ContributionOptimizer: deduct platform + advisor fees from growth rate
- [x] 2 protection premium actions: high premiums (>5% income), affordability warning (>10% income)
- [x] IncomeProtectionPolicy: added premium_frequency field + migration
- [x] 10-year fee impact added to pension Holdings tab (cumulative fees, lost growth, total impact)
- [x] IPT confirmed not applicable (life/CI/IP all exempt per TaxConfig)
- [x] Browser tested: all 3 pensions verified with 10-year fee impact rendering correctly
- [x] Deploy guide updated: March/March26Updates/deploy26.md

### Branch
- `fees` — ready for merge to main

---

## Outstanding Items

### Next Priority Tasks
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards
- [ ] Policy number not displaying in detail view (shows N/A despite being entered) — investigate

### Tier 3 (Future — from feesMap.md)
- [ ] Standardise all fee thresholds into PlanConfigService (currently hardcoded in seeders)
- [ ] Cross-module fee dashboard (total annual cost across investments + pensions + protection)

### Tech Debt (carried forward)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue: orphaned (never imported)
- [ ] DB enum missing step_child/partner — handler maps as workaround

### Known Issues
- [ ] DB pension field mapping mismatch (pre-existing — employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form (direct DB save pattern)

## Context for Next Session

Fee system gaps now closed for Tier 1 + 2. Pension actions will fire for high fees (matching investment module parity). Protection premium affordability actions now exist. PensionProjector and ContributionOptimizer now deduct all fees from growth projections (was platform-only before). The `fees` branch needs merging, building, and deploying. On production: run migration, reseed RetirementActionDefinitionSeeder + ProtectionActionDefinitionSeeder. Tier 3 items (threshold standardisation + cross-module dashboard) are documented in feesMap.md for future planning.
