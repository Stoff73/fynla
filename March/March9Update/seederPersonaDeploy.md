# Seeder & Persona Data Fix — March 9, Session 3

## Summary

Audited all 6 persona markdown files against the live database, found 4 discrepancies and 1 missing persona. Fixed all issues in both the JSON seeder data and markdown documentation.

---

## Issues Found & Fixed

### 1. Entrepreneur Will Section — Wrong Names (Critical)

**Problem:** The entrepreneur persona's will section referenced entirely different people from a copy-paste error.

| Field | Was (Wrong) | Now (Correct) |
|-------|-------------|---------------|
| Executor | David Chen (Father) | Wei Chen (Father) |
| Bequest 1 | David Chen (Father) | Wei Chen (Father) |
| Bequest 2 | Lin Chen (Mother) | Mei Chen (Mother) |
| Bequest 5 | Tom Harrison (Business Partner) | Marcus Wong (Business Partner) |
| Asset reference | DataFlow Analytics shares | Chen Tech Consulting Ltd shares |
| Executor notes | "Business partner Tom has first refusal" | "Business partner Marcus Wong has first refusal" |
| Letter box | "Marc Wong owns 40%" (typo) | "Marcus Wong owns 40%" |

**Files changed:**
- `resources/js/data/personas/entrepreneur.json` — 7 field fixes
- `appMapping/personaData/entrepreneur.md` — Will section corrected, Has Will set to Yes

### 2. Peak Earners — David's Current Account Balance

**Problem:** Markdown said £8,450 but database (seeded from JSON) had £25,000.

**Fix:** Updated `appMapping/personaData/peak_earners.md` balance to £25,000 (JSON was already correct).

### 3. DB Pensions — Missing Fields in Seeder

**Problem:** `spouse_pension_percent` and `pensionable_service_years` columns were always null despite JSON having the data.

**Fix:** Added both fields to `DBPension::create()` in `PreviewUserSeeder.php`.

| Persona | Pension | Spouse % | Service Years |
|---------|---------|----------|---------------|
| Peak Earners | NHS Pension (Sarah) | 50% | 18 |
| Widow | Teachers' Pension | 0% | 35 |
| Retired Couple | NHS Pension (Patricia) | 50% | 30 |
| Retired Couple | Civil Service (Harold) | 50% | 35 |

### 4. Missing Student Persona Documentation

**Problem:** Database had a `student` persona (Janice Taylor, id 85) but no markdown file existed.

**Fix:** Created `appMapping/personaData/student.md` with all data from DB:
- Janice Taylor, 21, University Student (Part-time Retail), Bristol
- Cash ISA (£1,200 at Monzo), LISA (£400 at Moneybox)
- Student Loan Plan 5 (£35,000)
- Monthly expenditure £750

### 5. Persona Data MOC — Wrong Names

**Problem:** `Persona Data.md` listed retired couple as "Robert & Patricia Williams".

**Fix:** Corrected to "Patricia & Harold Bennett". Added Student persona row.

---

## Files Changed

| File | Change |
|------|--------|
| `database/seeders/PreviewUserSeeder.php` | Added `spouse_pension_percent` and `pensionable_service_years` to DB pension creation |
| `resources/js/data/personas/entrepreneur.json` | Fixed will section names (Wei/Mei Chen, Marcus Wong, Chen Tech Consulting) |
| `appMapping/personaData/entrepreneur.md` | Fixed will section names, Has Will = Yes |
| `appMapping/personaData/peak_earners.md` | David's Current Account balance £8,450 → £25,000 |
| `appMapping/personaData/student.md` | **New file** — complete student persona documentation |
| `Persona Data.md` | Fixed retired couple names, added student row |

---

## Verification

After reseeding with `php artisan db:seed --class=PreviewUserSeeder --force`:

- All 7 personas seeded successfully (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple, student)
- DB pension `spouse_pension_percent` and `pensionable_service_years` now populated for all 4 DB pensions
- Entrepreneur will executor shows "Wei Chen (Father) & Pannone Corporate LLP"
- Entrepreneur bequests reference correct names (Wei Chen, Mei Chen, Marcus Wong)
- Business partner asset bequest references "Chen Tech Consulting Ltd"

---

## No Production Deployment Required

All changes are seeder data, persona JSON, and documentation. No PHP controllers, Vue components, or database schema changes.

To apply on any environment: `php artisan db:seed --class=PreviewUserSeeder --force`
