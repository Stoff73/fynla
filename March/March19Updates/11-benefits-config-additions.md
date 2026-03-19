# Benefits Configuration Additions — Tax-Free Childcare, Early Years, Child Benefit Warnings

**Date:** 19 March 2026
**Branch:** `logicFix`
**Commit:** `d7219e1`

## Summary

Added comprehensive UK benefits configuration to TaxConfigService for Tax-Free Childcare, Early Years Funding, and enhanced Child Benefit data including age limits, earning thresholds, and structured warning text. All values sourced centrally from TaxConfigService for use in onboarding helper text, dashboard warnings, and benefit eligibility checks.

## Changes

### TaxConfigurationSeeder — Child Benefit Enhancements

Added to existing `child_benefit` section:
- `age_limit_standard: 16` — benefit stops when child turns 16
- `age_limit_education: 20` — extends if in approved education/training
- `guardian_allowance_weekly: 21.75` — for caring for orphaned child
- 4 structured warnings:
  - Does not stop automatically — must opt out or pay HICBC via Self Assessment
  - HICBC based on higher earner — applies regardless of who claims
  - Still claim for National Insurance credits even if opting out of payments
  - Two-child limit applies to Universal Credit only, not Child Benefit itself

### TaxConfigurationSeeder — Tax-Free Childcare (new section)

| Config | Value |
|--------|-------|
| Government top-up rate | 25% (20p per 80p) |
| Max contribution per child | £2,000/year (£500/quarter) |
| Max disabled child | £4,000/year (£1,000/quarter) |
| Child age limit | Under 12 (under 17 disabled) |
| Min earnings | NMW × 16 hours/week (£183.04/wk for 2025/26) |
| Max income | £100,000 adjusted net income per parent |

4 warnings: cannot combine with UC/vouchers, both parents must work, £100k limit, reconfirm quarterly.

### TaxConfigurationSeeder — Early Years Funding (new section)

| Entitlement | Hours | Age | Income Test |
|-------------|-------|-----|-------------|
| Universal 15hrs | 15hrs/wk, 38wks/yr | 3-4 year olds | None |
| Working parents 30hrs | 30hrs/wk, 38wks/yr | 3-4 year olds | Min NMW×16hrs, max £100k |
| Working parents 2yr | 15hrs/wk, 38wks/yr | 2 year olds | Min NMW×16hrs, max £100k |
| Working parents under 2 | 15hrs/wk, 38wks/yr | 9 months+ | Min NMW×16hrs, max £100k |
| Disadvantaged 2yr | 15hrs/wk, 38wks/yr | 2 year olds | UC/tax credits income criteria |

4 warnings: term start dates, stretched hours option, top-up costs, income threshold.

### TaxConfigService — New Helper Methods

- `getTaxFreeChildcare(): array` — returns Tax-Free Childcare config with fallback defaults
- `getEarlyYearsFunding(): array` — returns Early Years Funding config with fallback defaults
- Existing `getChildBenefit()` now returns enhanced data including age limits and warnings

## Files Changed

- `database/seeders/TaxConfigurationSeeder.php` — added ~110 lines of config data
- `app/Services/TaxConfigService.php` — added 2 helper methods

## Testing

- 62/62 tests pass (Tax, Retirement, Dashboard suites)
- 18/18 seeders pass
- All values verified via tinker: child benefit warnings (4), TFC warnings (4), EY warnings (4), correct thresholds
