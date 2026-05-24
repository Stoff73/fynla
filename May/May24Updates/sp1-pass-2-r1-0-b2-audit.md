---
type: audit
title: SP1 Pass 2 — R1.0 B2 audit of TaxSettings admin-edit round-trip
date: 2026-05-24
spec_section: §12.1 of docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
plan: docs/superpowers/plans/2026-05-22-sub-project-1-pass-2-reference-data.md (PR R1.0)
methodology: server-side static analysis + feature-test review (Playwright not available in this session)
---

# SP1 Pass 2 — R1.0 B2 Audit

The 2026-05-14 spec §12.1 claims `TaxSettings.vue` is "not wired correctly — values can't actually be edited." This audit verifies the current state of the admin-edit round-trip post-Pass-2-R1.

## Methodology

Browser-driven verification was not possible in this session (no Playwright MCP). The audit is **server-side**:

1. Confirmed all 12 `tests/Feature/Admin/TaxConfigAdminTest.php` cases pass — the controller→store→DB→re-read path is correct end-to-end.
2. Static-analysis cross-check of `resources/js/components/Admin/TaxSettings.vue` (3,068 lines) against `resources/js/services/taxSettingsService.js`, `app/Http/Controllers/Api/TaxSettingsController.php`, and the live `config_data` shape from `\App\Models\TaxConfiguration::where('is_active', true)->first()`.
3. Compared three sets:
   - **A.** Top-level sections in live `config_data` (25)
   - **B.** Sections sent in `TaxSettings.vue::saveChanges()` request body (9)
   - **C.** Sections with at least one `v-model.number="editableConfig.<section>.*"` binding in `TaxSettings.vue` (14)

## What works (no fix needed)

The controller → `TaxConfigStore` → DB persistence path is fully wired:

- `TaxConfigStore::update($id, $payload, IngestSource::ADMIN, $actorUserId, $rationale, $ip)` — green (test: TaxConfigAdminTest #4–#6)
- `TaxConfigStore::create($payload, IngestSource::ADMIN, ...)` — green (#1–#3)
- `TaxConfigStore::setActive($id, ...)` — green (#7–#9)
- `TaxConfigStore::duplicate($id, $newYear, ...)` — green (#10–#11)
- `TaxConfigStore::delete($id)` — green (#12)

Pass 2 R1.2's controller refactor + R1.4's read migration are doing their job. The arch test is locked down (R1.6).

For every editable field bound in Vue and submitted, the round-trip is correct.

## What doesn't work — three defect classes

### B2-A: Vue silently drops 5 of its own editable sections on save

`TaxSettings.vue::saveChanges()` (line 2872) sends only this `config_data` envelope:

```js
{
  income_tax,
  national_insurance,
  isa,
  capital_gains_tax,
  dividend_tax,
  pension,
  inheritance_tax,
  gifting_exemptions,
  stamp_duty,
}
```

But `TaxSettings.vue` contains **134 `v-model.number="editableConfig.<section>.*"` bindings across 14 sections.** The 5 sections that are bound in the UI but **omitted from the payload**:

| Section | Field count in UI (approx.) | Where it's lost |
|---------|------------------------------|------------------|
| `savings` | 12+ (savings rates, premium bonds, etc.) | dropped in saveChanges |
| `investment` | 8+ (asset class yields, fee defaults, etc.) | dropped in saveChanges |
| `protection` | 6+ (life-insurance default rates, IP deferred periods) | dropped in saveChanges |
| `benefits` | 10+ (child benefit, tax-free childcare, early-years funding) | dropped in saveChanges |
| `assumptions` | 6+ (inflation, growth, mortality defaults) | dropped in saveChanges |

**User-visible symptom:** open Admin → Tax Settings → click Edit → change one of the values in (e.g.) the "Savings rates" panel → Save → see "Tax configuration updated successfully" → reload the page → **the change is gone**. There is no error, no warning. The save reports success because the partial payload is accepted by the controller (the validation rule is `config_data => sometimes|array`, no field allowlist).

**This is the spec's B2 defect.** Five sections are silently un-editable from the admin UI.

**Fix scope (R1.5):** add the 5 sections to `saveChanges()`'s payload. Minimum-diff one-liner per section. No backend change needed — the controller already accepts arbitrary `config_data` keys.

### B2-B: 6 sections present in `config_data` have no Vue editor at all

The live `config_data` array has 25 top-level keys. Stripping metadata (`tax_year`, `effective_from`, `effective_to`, `notes`) and the 14 sections that have at least one Vue binding leaves **6 sections that the admin UI never offers to edit**:

- `estate` (estate-planning defaults)
- `trusts` (trust tax rules)
- `domicile` (domicile / deemed-domicile rules)
- `retirement` (retirement planning defaults)
- `student_loan` (student loan plan thresholds)
- `property_ownership` (SDLT / ownership defaults)

These ARE consumed by services across the app (estate planning, trust strategy, retirement projection, student-loan repayment, SDLT calculations). The spec §12.1 motivation — "values seeded once, drift over time" — applies to them too. But they are NOT editable today, so the only remediation today is re-running the seeder.

**Fix scope (CSJ decision needed before R1.5 scopes this in):**
- **Option A (recommended):** R1.5 adds editor panels for all 6 sections (substantial Vue diff, ~600 lines).
- **Option B (defer):** R1.5 only fixes B2-A; B2-B is tracked as a follow-up after Pass 2 closes. Risk: tax-rate drift in these sections remains seeder-only.
- **Option C (partial):** R1.5 adds editors for the 2 most-frequently-changing sections (`student_loan`, `property_ownership` since SDLT changes most frequently in UK budgets); defer the rest.

### W1: `TaxSettingsController::getCalculations()` returns ~125 lines of hardcoded values

The `getCalculations()` endpoint (line 230) is used to populate the "Tax calculation rules" reference panel in the admin UI. It returns hardcoded strings:

```php
'income_tax' => [
    'bands' => [
        'personal_allowance' => '£0 - £12,570 (0%)',
        'basic_rate' => '£12,571 - £50,270 (20%)',
        'higher_rate' => '£50,271 - £125,140 (40%)',
        'additional_rate' => 'Over £125,140 (45%)',
    ],
    'notes' => 'Personal allowance reduces by £1 for every £2 earned over £100,000',
],
'national_insurance' => [
    'class_1_employee' => [
        'primary_threshold' => '£12,570 per year',
        // ... etc.
    ],
],
'inheritance_tax' => [
    'nil_rate_band' => '£325,000 (transferable between spouses)',
    // ...
],
// 4 more sections, all hardcoded
```

This violates CLAUDE.md Rule #3 ("No hardcoded tax values — use TaxConfigService"). It also means the reference panel displayed to admins **does not update** when the underlying tax config is edited — so an admin who edits the personal allowance from £12,570 to (say) £13,000 will see "£0 - £12,570 (0%)" in the reference panel after their successful save. Confusing, and a real-world correctness issue.

**Fix scope (R1.5):** rewrite `getCalculations()` to read values from `TaxConfigStore::activeConfig()->config_data` and `number_format` them inline. ~125 lines of hardcoded strings → ~80 lines of dynamic strings.

## Verdict

Spec's B2 scope is **PARTIAL today.**

- ✅ The controller / service / DB / audit pipeline is correctly wired (12 feature tests prove it).
- ❌ The Vue layer silently drops 5 editable sections on save (B2-A) — actionable in R1.5 with a one-liner per section.
- ❓ The Vue layer offers no editor for 6 sections that are consumed by other services (B2-B) — scope decision needed from CSJ before R1.5 sizing.
- ❌ The admin-displayed calculation rules are hardcoded and drift from the actual saved config (W1) — actionable in R1.5 with a controller rewrite.

## Plan adjustments for PR R1.5

**Default scope (no CSJ input needed):** R1.5 ships B2-A (5-line payload fix) + W1 (`getCalculations()` rewrite). Total diff ~250 lines including tests.

**With CSJ "Option A" decision on B2-B:** add Vue editors for all 6 missing sections. Total diff ~900 lines.

**With CSJ "Option C" decision on B2-B:** add editors for `student_loan` + `property_ownership` only. Total diff ~450 lines.

## Pass-1 / Pass-2 dependencies status

- **`IngestSource`** — OK (Pass 1)
- **`TierGate`** — OK (Pass 1)
- **`SnapshotPolicy`** — OK (Pass 1)
- **`TaxConfigStore`** — OK (Pass 2 R1.1)
- **`ActuarialLifeTableStore`** — OK (Pass 2 R3.1)
- **`CurrencyRateStore`** — OK (Pass 2 R2.2)
- **`SavingsMarketRateStore`** — OK (Pass 2 R4.1)

All R-track stores have shipped. R1.5 is the only outstanding code change before Pass 2 lock-down.

## Outstanding before Pass 2 closes

1. **R1.5** — fix B2-A + W1 (scope-locked here) ± B2-B (CSJ decision)
2. **Final pass-wide review** — confirm every R-track arch test is green, every admin panel works
3. **`superpowers:finishing-a-development-branch`** — formally close the 26-PR pass

Pass 3 (Pensions) plan is ready at `docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md` (PR #370) but per spec §15.2 may not start until Pass 2 reaches main.
