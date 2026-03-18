# Advisor Dashboard — Test Report

**Date:** 18 March 2026
**Tester:** Claude Code
**Method:** Pest unit/feature tests + Playwright browser testing
**Environment:** localhost:8000 (dev), chris@fynla.org (admin + advisor)

---

## Pest Test Results

### Before Fixes (Test Case Collision)

All 5 advisor test files crashed with:
```
ERROR: Test case `Tests\TestCase` can not be used. The folder already uses the test case `Tests\TestCase`
```

**Root cause:** Redundant `uses(TestCase::class, RefreshDatabase::class)` in test files — already defined globally in `tests/Pest.php` for `Feature` and `Unit/Services` directories.

**Fix applied:** Removed redundant `uses()` from:
- `tests/Feature/Api/AdvisorControllerTest.php`
- `tests/Feature/Middleware/AdvisorMiddlewareTest.php`
- `tests/Unit/Services/Advisor/AdvisorDashboardServiceTest.php`
- `tests/Unit/Services/Advisor/AdvisorImpersonationServiceTest.php`

### After Fixes — All Pass

| Test File | Tests | Assertions | Duration | Status |
|-----------|-------|------------|----------|--------|
| AdvisorControllerTest.php | 13 | 71 | 12.21s | ALL PASS |
| AdvisorMiddlewareTest.php | 4 | 8 | 13.94s | ALL PASS |
| UserModuleTrackingServiceTest.php | 6 | 17 | 11.71s | ALL PASS |
| AdvisorDashboardServiceTest.php | 6 | 38 | 10.38s | ALL PASS |
| AdvisorImpersonationServiceTest.php | 8 | 27 | 10.08s | ALL PASS |
| **TOTAL** | **37** | **161** | **58.32s** | **ALL PASS** |

### Full Suite Results

```
Tests: 1990 passed, 23 failed (8516 assertions)
Duration: 157.47s
```

The 23 failures are pre-existing in `TaxConfigurationTest.php` (duplicate `admin@fps.com` entry) — not advisor-related.

---

## Browser Test Results

### Test Environment Setup

1. Created `chris@fynla.org` user via tinker (not seeded — requires registration)
2. Set `is_admin = true` and `is_advisor = true` via DB update
3. Ran `AdvisorClientSeeder` — discovered BUG-8 (seeder returned early due to `update()` returning 0)
4. Fixed seeder, re-ran — 5 clients, 19 activities created
5. Missing `widow` persona (Margaret Thompson) — 5/6 expected clients

### Login Flow

| Step | Action | Result | Status |
|------|--------|--------|--------|
| Navigate to /login | Page loaded | Login form displayed | PASS |
| Fill email: chris@fynla.org | Typed | Field populated | PASS |
| Fill password: Password1! | Typed | Field populated | PASS |
| Click Sign In | Clicked | Verification code screen appeared | PASS |
| Enter verification code | Entered 6 digits | Redirected to /dashboard | PASS |

### Page: Advisor Dashboard (/advisor)

| Element | Expected | Actual | Status |
|---------|----------|--------|--------|
| Layout: Top bar with "ADVISOR VIEW" | Shows | Shows correctly, violet badge | PASS |
| Layout: Sidebar navigation | 7 links | All 7 links present (Dashboard, All Clients, Reviews Due, Communications, Suitability Reports, Activity Log, Settings) | PASS |
| Layout: User name in top bar | "Chris Jones" | "Chris Jones" with CJ initials | PASS |
| Sidebar badge: All Clients count | "5" | "5" | PASS |
| Sidebar badge: Reviews Due count | "2" | "2" | PASS |
| Stat: Active Clients | 5 | 5 | PASS |
| Stat: Reviews Due | 2 (with "2 overdue" sub-label) | 2 with "2 overdue" | PASS |
| Stat: Communications | 0 this week | 0 | PASS |
| Stat: Reports Sent | 2 this month | 2 | PASS |
| Client Table: 5 rows | 5 rows with names | 5 rows: James & Emily Carter, David & Sarah Mitchell, Alex Chen, John Morgan, Patricia & Harold Bennett | PASS |
| Client Table: Initials avatars | Colored circles with initials | JC, DM, AC, JM, PB shown | PASS |
| Client Table: Module dots (P S I R E) | 5 dots per client | 5 dots shown per client | PASS |
| Client Table: Last Review dates | Dates with relative time | Correct dates + "65 days ago", "93 days ago", etc. | PASS |
| Client Table: Last Communication | Type + date for clients with comms | Shows for some clients, "--" for others | PARTIAL |
| Client Table: Last Report | Report type + date | Shows raw `protection_review` for Carter, "--" for others | FAIL |
| Client Table: Status badges | "Active" green | "Active" shown for all | PASS |
| Client Table: View button | Navigates to detail | Navigates but page is blank (BUG-1) | FAIL |
| Client Table: Enter Profile button | Present | Button present and clickable | PASS |
| Reviews Due panel: Client names | Name + overdue badge | **MISSING** — h3 heading is empty, badge shows "118 days overdue" / "93 days overdue" | FAIL |
| Reviews Due panel: Review details | "Annual Review — Last reviewed [date]" | Shows "Annual Review — Last reviewed [date]" | PARTIAL |
| Recent Activity: 5 entries | Type-specific labels + summaries | Shows summaries correctly but ALL show "Activity" label instead of Email/Phone/Meeting | FAIL |
| Recent Activity: Activity dates | Dates with relative time | Correct dates + relative time | PASS |

### Page: All Clients (/advisor/clients)

| Element | Expected | Actual | Status |
|---------|----------|--------|--------|
| Page heading | "All Clients" | "All Clients" | PASS |
| Search box | Present | Present, functional UI | PASS |
| Status filter dropdown | All Statuses, Active, Review Due | All 3 options present | PASS |
| Module status legend | P S I R E with color key | Complete, Partial, No Data, Skipped shown | PASS |
| Client table | 5 rows with full data | 5 rows, same rendering as dashboard table | PASS |
| View/Enter Profile buttons | Present for each row | Present and clickable | PASS |

### Page: Client Detail (/advisor/clients/{id})

| Element | Expected | Actual | Status |
|---------|----------|--------|--------|
| Page renders | Client profile card, modules, timeline | **BLANK WHITE PAGE** — Vue error: `Cannot read properties of null (reading 'id')` | FAIL |
| Back button | Present | Not visible (page blank) | FAIL |
| Enter Profile button | Present | Not visible (page blank) | FAIL |
| Profile card | Avatar, name, type, dates | Not visible (page blank) | FAIL |
| Module status | P S I R E dots with legend | Not visible (page blank) | FAIL |
| Review information | Next due, last review, frequency, status | Not visible (page blank) | FAIL |
| Activity timeline | List of activities with icons | Not visible (page blank) | FAIL |

### Page: Reviews Due (/advisor/reviews)

| Element | Expected | Actual | Status |
|---------|----------|--------|--------|
| Page heading | "Reviews Due" | "Reviews Due" | PASS |
| Overdue count badge | "2 overdue" (raspberry) | "2 overdue" badge showing | PASS |
| Review card 1: Client name | "Alex Chen" | **EMPTY** — h3 heading is blank | FAIL |
| Review card 1: Overdue badge | "118 days overdue" | "118 days overdue" (raspberry) | PASS |
| Review card 1: Last Review | "20 Nov 2025" | "20 Nov 2025" | PASS |
| Review card 1: Frequency | "Annual" | "Annual" | PASS |
| Review card 1: Next Due | Date in raspberry | **"--"** — shows dash instead of date | FAIL |
| Review card 1: View Client button | Present | Present, clickable | PASS |
| Review card 2: Client name | "David & Sarah Mitchell" | **EMPTY** | FAIL |
| Review card 2: Overdue badge | "93 days overdue" | "93 days overdue" | PASS |

### Page: Activity Log (/advisor/activities)

| Element | Expected | Actual | Status |
|---------|----------|--------|--------|
| Page heading | "Activity Log" | "Activity Log" | PASS |
| Log Activity button | Present (raspberry) | Present | PASS |
| Client filter dropdown | All 5 clients listed | "All Clients" + 5 client names | PASS |
| Type filter dropdown | 7 activity types | All 7 types listed | PASS |
| Date range filters | From/To inputs | Both present | PASS |
| Activity entries | 19 activities with icons, labels, dates | **"No activities found"** — empty state shown despite 19 activities in database | FAIL |

### Page: Suitability Reports (/advisor/reports)

| Element | Expected | Actual | Status |
|---------|----------|--------|--------|
| Page heading | "Suitability Reports" | "Suitability Reports" | PASS |
| Reports table | 7 reports with client, type, dates | **"No suitability reports"** — empty state shown despite 7 reports in database | FAIL |

### Page: Communications (/advisor/activities?type=email,phone,letter,meeting)

| Element | Expected | Actual | Status |
|---------|----------|--------|--------|
| Filtered activity list | Communications only | **NOT TESTED** — dependent on Activity Log fix (BUG-2) |

### Impersonation Flow

| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| Click "Enter Profile" on dashboard | Enter client view with violet banner | **NOT TESTED** — dependent on Client Detail fix (BUG-1) |
| Violet banner in AppLayout | Shows client name + exit button | Component exists, import correct, conditional rendering present in AppLayout | CODE REVIEW ONLY |
| Click "Exit" on banner | Return to advisor dashboard | **NOT TESTED** |

---

## Test Summary

| Category | Total | Pass | Fail | Not Tested |
|----------|-------|------|------|------------|
| Pest Tests | 37 | 37 | 0 | 0 |
| Dashboard Elements | 22 | 16 | 6 | 0 |
| All Clients Page | 6 | 6 | 0 | 0 |
| Client Detail Page | 7 | 0 | 7 | 0 |
| Reviews Due Page | 10 | 5 | 5 | 0 |
| Activity Log Page | 6 | 5 | 1 | 0 |
| Reports Page | 2 | 1 | 1 | 0 |
| Communications | 1 | 0 | 0 | 1 |
| Impersonation | 3 | 0 | 0 | 3 |
| **TOTAL** | **94** | **70** | **20** | **4** |

---

## Fixes Applied During This Audit

| Fix | File | Description |
|-----|------|-------------|
| Test case collision | 4 test files | Removed redundant `uses(TestCase::class)` calls |
| Seeder early return | `AdvisorClientSeeder.php` | Changed to check user existence first, then update is_advisor |

---

## Conclusion

The advisor backend is production-quality — clean architecture, proper middleware, comprehensive tests. But the frontend has **systematic field name mismatches** between the API responses and Vue templates, plus **pagination response parsing bugs**, that render 5 of 6 advisor pages either broken or missing critical data. These are straightforward fixes (field renames + pagination unwrapping) but they must be done before this feature is usable.

See `advisorGaps.md` for the complete bug list with fix instructions.
