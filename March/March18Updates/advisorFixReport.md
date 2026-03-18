# Advisor Dashboard — Fix & Test Report

**Date:** 18 March 2026
**Status:** ALL BUGS FIXED AND VERIFIED

---

## Fixes Implemented

### Backend Fixes

**1. AdvisorController::clientDetail() — Response shape transformation**
- **File:** `app/Http/Controllers/Api/AdvisorController.php`
- **Problem:** Returned raw Eloquent `AdvisorClient` model with nested `client` relationship. Vue expected flat object with `display_name`, `module_status`, etc.
- **Fix:** Transform response to flat shape matching `getClientList()` format. Includes `display_name` (with spouse coupling), `module_status` (via `UserModuleTrackingService`), `next_review_due`, `review_frequency_months`, `assigned_date`, and formatted `activities` array.
- **Test updated:** `AdvisorControllerTest` assertion changed from `['id', 'advisor_id', 'client_id', 'status', 'client']` to `['id', 'client_id', 'display_name', 'status', 'module_status', 'activities']`.

**2. ClientActivityService::listForAdvisor() — Add client_name to activity responses**
- **File:** `app/Services/Advisor/ClientActivityService.php`
- **Problem:** Activities returned raw `ClientActivity` model with nested `client` relationship. Frontend expected `client_name` as a string field.
- **Fix:** Transform paginated collection to add `client_name` (first_name + surname) as an attribute on each activity.

**3. AdvisorClientSeeder — Fix early return bug**
- **File:** `database/seeders/AdvisorClientSeeder.php`
- **Problem:** `DB::update()` returns 0 when `is_advisor` is already `true` (no rows modified). Seeder treated this as "user not found" and returned early, creating zero advisor-client records.
- **Fix:** Changed to first check user existence with `User::where()`, then update `is_advisor`.

### Frontend Fixes

**4. AdvisorDashboard.vue — Recent Activity type labels**
- **Problem:** Template used `activity.type` but API returns `activity_type`. Also used `'report'` as type value but API uses `'suitability_report'`.
- **Fix:** Changed all `activity.type` to `activity.activity_type` in icon conditionals, `activityIconClass()`, and `activityLabel()`. Updated `'report'` case to `'suitability_report'`.

**5. AdvisorDashboard.vue — Reviews Due panel client names**
- **Problem:** `review.client_name` (undefined) used instead of `review.display_name`. `review.review_type` (undefined) used for label.
- **Fix:** Changed to `review.display_name`. Added `formatReviewFrequency(months)` method for integer month values. Added `formatReportType()` for Last Report column.

**6. AdvisorReviewsDue.vue — Field name mismatches**
- **Problem:** `review.client_name` → should be `display_name`. `review.next_review_date` → should be `next_review_due`. `review.review_frequency` → should be `review_frequency_months`.
- **Fix:** Updated all field references. Updated `formatFrequency()` to handle integer month values (6 = "Semi-Annual", 12 = "Annual").

**7. AdvisorClientDetail.vue — Field names + data shape**
- **Problem:** Template referenced `client.next_review_date`, `client.review_frequency`, `client.client_type`, `isReviewOverdue` checked wrong field.
- **Fix:** Updated to `next_review_due`, `review_frequency_months`. Updated `formatFrequency()` to handle integers. Fixed `isReviewOverdue` computed. Fixed activities pagination parsing.

**8. AdvisorActivityLog.vue — Pagination response parsing**
- **Problem:** `this.activities = data.data || []` — `data.data` was the pagination wrapper object `{current_page, data: [...]}`, not the activities array.
- **Fix:** Added unwrapping: `const paginated = response.data || response;` then `this.activities = paginated.data || [];`.

**9. AdvisorReports.vue — Pagination parsing + field names**
- **Problem:** Same pagination bug as Activity Log. Also `report.acknowledged_date` should be `report.report_acknowledged_date`. Raw `report_type` displayed as snake_case.
- **Fix:** Fixed pagination unwrapping. Changed to `report.report_acknowledged_date`. Added `formatReportType()` to capitalise and space report type names.

**10. SideMenu.vue — Added Advisor Dashboard link**
- **Problem:** No "Advisor" link in main app sidebar. Advisors had to manually navigate to `/advisor`.
- **Fix:** Added `isAdvisor` computed property from `auth/isAdvisor` getter. Added "Advisor" sidebar section with "Advisor Dashboard" link, conditionally shown for advisor users. Added `/advisor` path to section expansion logic.

**11. AdvisorImpersonationMiddleware — Applied to API middleware group**
- **File:** `app/Http/Kernel.php`, `app/Http/Middleware/AdvisorImpersonationMiddleware.php`
- **Problem:** Middleware was registered as alias but never applied to any route group. Impersonation stored cache state but never swapped `auth()->user()` on subsequent API requests, so clicking "Enter Profile" showed the advisor's empty dashboard instead of the client's financial data.
- **Fix:** Added middleware to the `api` middleware group in `Kernel.php`. Added route skip for `api/advisor/*` so the advisor retains their identity on advisor endpoints while impersonating a client.

**12. Navbar — Added Advisor link to top navigation**
- **File:** `resources/js/components/Navbar.vue`
- **Problem:** No way to access advisor dashboard from the main app top navigation.
- **Fix:** Added violet "Advisor" button next to Admin button, shown when `isAdvisor` is true.

**13. AdvisorLayout — Replaced text logo with Fynla image**
- **File:** `resources/js/layouts/AdvisorLayout.vue`
- **Problem:** Top bar showed plain text "fynla" instead of the actual logo.
- **Fix:** Replaced with `<img src="/images/logos/LogoHiResFynlaLight.png">`.

### Test File Fixes

**14. Pest test case collisions — 4 files**
- **Files:** `AdvisorControllerTest.php`, `AdvisorMiddlewareTest.php`, `AdvisorDashboardServiceTest.php`, `AdvisorImpersonationServiceTest.php`
- **Problem:** Redundant `uses(TestCase::class, RefreshDatabase::class)` conflicted with `tests/Pest.php` global bindings.
- **Fix:** Removed redundant `uses()` calls.

---

## Pest Test Results — ALL PASS

| Test File | Tests | Assertions | Status |
|-----------|-------|------------|--------|
| AdvisorControllerTest.php | 13 | 62 | ALL PASS |
| AdvisorMiddlewareTest.php | 4 | 8 | ALL PASS |
| UserModuleTrackingServiceTest.php | 6 | 17 | ALL PASS |
| AdvisorDashboardServiceTest.php | 6 | 38 | ALL PASS |
| AdvisorImpersonationServiceTest.php | 8 | 27 | ALL PASS |
| **TOTAL** | **37** | **162** | **ALL PASS** |

---

## Browser Test Results — ALL PAGES VERIFIED

### Dashboard (/advisor)
| Element | Result |
|---------|--------|
| Stats: 5 Active Clients, 2 Reviews Due (2 overdue), 1 Communication (this week), 2 Reports | PASS |
| Client table: 5 rows with names, initials, module dots, dates | PASS |
| Client table: Last Communication shows "Email - 18 Mar 2026 yesterday" for Alex Chen | PASS |
| Client table: Last Report shows "12 Mar 2026 Protection Review" for Carter | PASS |
| Reviews Due panel: "Alex Chen" 118 days overdue, "David & Sarah Mitchell" 93 days overdue | PASS |
| Reviews Due panel: "Annual Review" label formatted correctly | PASS |
| Recent Activity: "Email sent", "Suitability report sent", "Phone call" type labels | PASS |
| Recent Activity: New activity "Sent tax year planning reminder" at top | PASS |
| Sidebar: "Advisor Dashboard" link visible in main app nav | PASS |

### Client Detail (/advisor/clients/{id})
| Element | Result |
|---------|--------|
| Page renders (was blank before) | PASS |
| Name: "James & Emily Carter" (coupled) | PASS |
| Assigned: "1 Jun 2025" | PASS |
| Review Schedule: "Semi-Annual" (from integer 6) | PASS |
| Module Status: P S I R E dots with legend | PASS |
| Review Info: Next Due "12 Jul 2026", Last "12 Jan 2026", Frequency "Semi-Annual" | PASS |
| Activity Timeline: 4 activities with "Suitability Report", "Email", "Phone Call" labels | PASS |
| Back to Clients button: present | PASS |
| Enter Profile button: present | PASS |

### Reviews Due (/advisor/reviews)
| Element | Result |
|---------|--------|
| "2 overdue" badge | PASS |
| Card 1: "Alex Chen" heading, 118 days overdue, Last Review 20 Nov 2025, Frequency Annual, Next Due 20 Nov 2025 | PASS |
| Card 2: "David & Sarah Mitchell" heading, 93 days overdue, Last Review 15 Dec 2025, Frequency Annual, Next Due 15 Dec 2025 | PASS |
| View Client buttons present | PASS |

### Activity Log (/advisor/activities)
| Element | Result |
|---------|--------|
| 20 activities showing (19 seeded + 1 created) | PASS |
| Type labels: Email, Phone Call, Meeting, Suitability Report | PASS |
| Client name badges: John Morgan, James Carter, Alex Chen, etc. | PASS |
| Filters: 5 clients dropdown, 7 types dropdown, date range | PASS |
| Log Activity button opens modal | PASS |
| Modal: Client dropdown, Type dropdown, Summary, Details, Date, Follow-up Date | PASS |
| Modal: Save Activity creates new entry and refreshes list | PASS |

### Suitability Reports (/advisor/reports)
| Element | Result |
|---------|--------|
| 6 reports showing (was "No suitability reports" before) | PASS |
| Client names: James Carter, John Morgan, Patricia Bennett, David Mitchell, Alex Chen | PASS |
| Report types formatted: "Protection Review", "Savings Review", "Annual Review", "Pension Review" | PASS |
| Sent dates, summaries, Pending status badges | PASS |

### All Clients (/advisor/clients)
| Element | Result |
|---------|--------|
| 5 clients with full data | PASS |
| Search box and status filter present | PASS |
| Module dots, review dates, action buttons | PASS |

---

## Remaining Known Issues (Low Priority)

1. **Missing `widow` persona** (Margaret Thompson) — Not in preview users table. 5/6 expected clients.
2. **N+1 query in AdvisorDashboardService** — `User::find($client->spouse_id)` in loops. Performance optimisation.
3. **23 pre-existing test failures** in `TaxConfigurationTest.php` — duplicate `admin@fps.com` entry. Not advisor-related.

---

## Files Changed

### Backend
| File | Change |
|------|--------|
| `app/Http/Controllers/Api/AdvisorController.php` | Transform `clientDetail()` response to flat shape |
| `app/Services/Advisor/ClientActivityService.php` | Add `client_name` to activity list responses |
| `database/seeders/AdvisorClientSeeder.php` | Fix early return when `is_advisor` already true |

### Frontend
| File | Change |
|------|--------|
| `resources/js/views/Advisor/AdvisorDashboard.vue` | Fix `activity.type` → `activity_type`, `review.client_name` → `display_name`, add `formatReviewFrequency()`, `formatReportType()` |
| `resources/js/views/Advisor/AdvisorReviewsDue.vue` | Fix `client_name` → `display_name`, `next_review_date` → `next_review_due`, `review_frequency` → `review_frequency_months`, update `formatFrequency()` |
| `resources/js/views/Advisor/AdvisorClientDetail.vue` | Fix field names, `formatFrequency()` for integers, `isReviewOverdue` computed, activities pagination |
| `resources/js/views/Advisor/AdvisorActivityLog.vue` | Fix pagination response unwrapping |
| `resources/js/views/Advisor/AdvisorReports.vue` | Fix pagination unwrapping, `acknowledged_date` → `report_acknowledged_date`, add `formatReportType()` |
| `resources/js/components/SideMenu.vue` | Add Advisor Dashboard link for advisor users |
| `resources/js/components/Navbar.vue` | Add violet Advisor button to top navbar for advisor users |

### Infrastructure
| File | Change |
|------|--------|
| `app/Http/Kernel.php` | Added `AdvisorImpersonationMiddleware` to `api` middleware group (was only registered as alias, never applied) |
| `app/Http/Middleware/AdvisorImpersonationMiddleware.php` | Added skip for `/api/advisor/*` routes so advisor retains identity on advisor endpoints while impersonating |

### Logo
| File | Change |
|------|--------|
| `resources/js/layouts/AdvisorLayout.vue` | Replaced text "fynla" with `LogoHiResFynlaLight.png` image in top bar |

### Tests
| File | Change |
|------|--------|
| `tests/Feature/Api/AdvisorControllerTest.php` | Remove redundant `uses()`, update client detail assertion |
| `tests/Feature/Middleware/AdvisorMiddlewareTest.php` | Remove redundant `uses()` |
| `tests/Unit/Services/Advisor/AdvisorDashboardServiceTest.php` | Remove redundant `uses()` |
| `tests/Unit/Services/Advisor/AdvisorImpersonationServiceTest.php` | Remove redundant `uses()` |
