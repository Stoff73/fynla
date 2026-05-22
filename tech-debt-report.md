# Tech Debt Report — Session 2026-05-22 (session 3 EOD)

**Files analysed:** 11 (R1 store + R1 controller + R1 service + R1 seeder + 5 new/edited tests + 1 hotfix)
**Issues found:** 5
**Severity breakdown:** 0 critical, 1 warning, 4 suggestions / pre-existing

All 11 files were committed and pushed via PRs #364 → #368 plus direct hotfix `3506d70`. Working tree clean at report time. R1 + R2 + hotfix all deployed to csjones — smoke green.

## Critical Issues

None.

## Warnings

### W1 — Hardcoded UK tax band display strings in `getCalculations()`

- **File:** `app/Http/Controllers/Api/TaxSettingsController.php` lines 232–355 (~125 lines)
- **Category:** Convention violation (Key Rule #3: No Hardcoded Tax Values)
- **What's wrong:** `getCalculations()` returns a static array with explicit values like `'£0 - £12,570 (0%)'`, `'£325,000 (transferable between spouses)'`, `'£60,000 per tax year'`. These are tax-year-dependent values that should come from `TaxConfigService`.
- **Suggested fix:** Replace literal strings with `TaxConfigService` lookups (`$this->taxConfig->get('income_tax.personal_allowance')` etc.) and format them at render time. Estimated 30-line refactor.
- **Predates this session.** R1.2's controller rewrite preserved this method verbatim per scope discipline — I touched the file but not this method. Worth picking up in R1.5 if the B2 audit surfaces it, or as a standalone follow-up.

## Suggestions / Pre-existing observations

### I1 — `Cache::flush()` clobbers the entire cache, not just tax-affected entries

- **File:** `app/Http/Controllers/Api/TaxSettingsController.php` line 407 (in `flushAgentCaches()`)
- **Category:** Complexity / blunt instrument
- **What's wrong:** Activating a tax year wipes every cached agent analysis for every user, plus every other cache entry in the app. Comment acknowledges "admin-only, rare operation" but at scale this evicts cold paths that re-warming will be expensive on.
- **Suggested fix:** Switch to tag-based invalidation (`Cache::tags(['tax-aware'])->flush()`) and tag agent analyses on write. Out of scope for R1 — this is preserved behaviour from before the store refactor.

### I2 — `TaxConfigService.php` is 677 lines (over the 500-line guideline)

- **File:** `app/Services/TaxConfigService.php`
- **Category:** Complexity (file length)
- **What's wrong:** The file mixes generic readers (`get()`, `has()`, `getAll()`), typed domain accessors (`getIncomeTax()`, `getInheritanceTax()`, `getISAAllowances()`, etc.), and property-ownership helpers in a single class.
- **Suggested fix:** Candidate for splitting into `TaxConfigReader` (generic) + `TaxConfigDomain` (typed domain accessors), backed by the same store. **Pre-existing**; R1.4 only touched the constructor, `loadActiveConfig()`, `clearCache()`, and removed `getModel()` — about 50 lines net.

### I3 — `TaxConfigurationSeeder.php` is 1525 lines

- **File:** `database/seeders/TaxConfigurationSeeder.php`
- **Category:** Complexity (file length)
- **What's wrong:** Six `getTaxConfig202122()` … `getTaxConfig202627()` methods, each carrying ~250 lines of static UK tax configuration. Annual diffs touch the file even when only one year's values change.
- **Suggested fix:** Split per-year configs into `database/seeders/tax-configs/{year}.php` files (or YAML/JSON loaded by the seeder). **Pre-existing**; R1.3 only modified the `run()` method and imports.

### I4 — `TaxConfigServiceTest.php` uses old PHPUnit `function test_xxx()` syntax (26 methods)

- **File:** `tests/Unit/Services/TaxConfigServiceTest.php`
- **Category:** Inconsistency with project test convention (`tests/CLAUDE.md` prefers Pest `describe()/it()`)
- **What's wrong:** The file uses classic PHPUnit `test_xxx()` methods throughout, not Pest closures. New tests added during R1 (in `TaxConfigStoreTest`, `TaxConfigAdminTest`, `TaxConfigNormaliserTest`) all use Pest syntax — this file is the odd one out.
- **Suggested fix:** Migrate to Pest syntax during a quiet refactor session. **Pre-existing**; R1.4 only removed the obsolete `test_get_model_returns_tax_configuration_model` test (covered the dropped `getModel()` method).

---

## Summary

- 0 critical, 1 warning, 4 suggestions
- The only session-3-introduced concern worth flagging is W1 (`getCalculations()` hardcoded values), and even that one was preserved-not-introduced — R1.2's controller rewrite kept it verbatim per scope discipline. R1.5 (B2 admin-edit audit) is the natural place to address it.
- I1 / I2 / I3 / I4 are pre-existing tech-debt observations on touched files, flagged for the backlog. None are session-3 regressions.

**Top 3 most impactful:**
1. W1 — `getCalculations()` hardcoded values (eventually risks displaying stale tax-year text in admin UI when an admin activates a new year)
2. I1 — `Cache::flush()` blunt invalidation (operationally noisy at scale, not a correctness issue)
3. I3 — `TaxConfigurationSeeder` length (annual update pain)

No critical issues require fixing. All R1 code (#364 → #368) plus the hotfix (#3506d70) is safe in its current state and is live on csjones (d3e1cf6).

---
*Generated by tech-debt-session skill — 2026-05-22 session 3 EOD*
