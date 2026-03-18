# Advisor Dashboard — Gaps Report

**Date:** 18 March 2026
**Auditor:** Claude Code (full code audit + Pest tests + browser testing)
**Implementation:** PR #136 (merged to main)

---

## Summary

**Backend: Solid.** All 20 files exist, architecture is correct, 37/37 Pest tests pass (after fixing test case collisions).

**Frontend: Broken in multiple critical ways.** Field name mismatches between backend API responses and Vue templates cause blank pages, missing data, and empty states across 5 of 6 advisor views. Only the main Dashboard and All Clients list work partially.

---

## CRITICAL BUGS (Application-breaking)

### BUG-1: Client Detail Page — Completely Blank (White Screen)

**File:** `resources/js/views/Advisor/AdvisorClientDetail.vue`
**Severity:** CRITICAL
**Error:** `Cannot read properties of null (reading 'id')` in render function

**Root Cause:** The `/api/advisor/clients/{id}` endpoint returns a raw `AdvisorClient` Eloquent model with nested `client` relationship:

```json
{
  "id": 34,
  "advisor_id": 1604,
  "client_id": 1058,
  "status": "active",
  "next_review_due": "2026-07-11T23:00:00.000000Z",
  "last_review_date": "2026-01-12T00:00:00.000000Z",
  "review_frequency_months": 6,
  "notes": "{\"avatar_colour\":\"violet-500\"}",
  "client": { "id": 1058, "first_name": "James", "surname": "Carter", ... }
}
```

But the Vue template expects a **flat, transformed object** with fields like:
| Template expects | API actually returns |
|-----------------|---------------------|
| `client.display_name` | Does not exist (need `client.client.first_name + client.client.surname`) |
| `client.module_status` | Does not exist (need separate `/modules` API call) |
| `client.next_review_date` | `client.next_review_due` |
| `client.review_frequency` | `client.review_frequency_months` (integer, not string) |
| `client.client_type` | Does not exist |

**Fix needed:** Either:
- (A) Transform the API response in `AdvisorController::clientDetail()` to match the flat shape returned by `getClientList()`
- (B) Update the Vue component to handle the nested `AdvisorClient + client` relationship model

---

### BUG-2: Activity Log Page — Shows "No activities found" Despite 19 Activities in Database

**File:** `resources/js/views/Advisor/AdvisorActivityLog.vue` (lines 310-319)
**Severity:** CRITICAL

**Root Cause:** Pagination response double-wrapping.

The API returns:
```json
{ "success": true, "data": { "current_page": 1, "data": [...activities...], "last_page": 1 } }
```

`advisorService.getActivities()` returns `response.data` = `{ success: true, data: { current_page: 1, data: [...] } }`.

The component does:
```javascript
const data = await advisorService.getActivities(params);
this.activities = data.data || [];  // data.data = pagination object, NOT the array
```

`data.data` is the pagination wrapper `{ current_page: 1, data: [...] }` — an object, not an array. `this.activities` gets set to this object. `activities.length` is `undefined` so the empty state shows.

**Fix needed:** Change line to: `this.activities = data.data?.data || data.data || [];`
Or better: `this.activities = Array.isArray(data.data) ? data.data : (data.data?.data || []);`

---

### BUG-3: Suitability Reports Page — Shows "No suitability reports" Despite 7 Reports in Database

**File:** `resources/js/views/Advisor/AdvisorReports.vue` (lines 99-105)
**Severity:** CRITICAL

**Root Cause:** Identical pagination parsing bug as BUG-2.

```javascript
const data = await advisorService.getActivities({ activity_type: 'suitability_report' });
this.reports = data.data || [];  // pagination object, not array
```

**Additional issue:** Even when fixed, `report.client_name` (line 33) won't work. The API returns activities with nested `client` relationship (`report.client.first_name` + `report.client.surname`), but the template expects `report.client_name`.

**Fix needed:**
1. Fix pagination parsing: `this.reports = data.data?.data || data.data || [];`
2. Fix client name: either add `client_name` to the backend response, or use `report.client?.first_name + ' ' + report.client?.surname` in the template
3. Fix acknowledged date: template uses `report.acknowledged_date` but API returns `report.report_acknowledged_date`

---

## HIGH-SEVERITY BUGS (Data Not Showing)

### BUG-4: Reviews Due Page — Client Names Missing, Next Due Date Shows "--"

**File:** `resources/js/views/Advisor/AdvisorReviewsDue.vue`
**Severity:** HIGH

**Field name mismatches:**
| Template expects | API returns | Line |
|-----------------|-------------|------|
| `review.client_name` | `review.display_name` | 27, 281 (dashboard) |
| `review.next_review_date` | `review.next_review_due` | 57 |
| `review.review_frequency` | `review.review_frequency_months` (integer) | 48 |
| `review.review_type` | Does not exist | 290 (dashboard) |

**Result:** Review cards show with overdue badges but NO client name (h3 is empty) and Next Due shows "--".

**Fix needed:** Update template to use correct field names: `display_name`, `next_review_due`, `review_frequency_months`. Add a frequency formatter that handles integers (6 = "Semi-Annual", 12 = "Annual").

---

### BUG-5: Dashboard Recent Activity — Shows "Activity" Instead of Type-Specific Labels

**File:** `resources/js/views/Advisor/AdvisorDashboard.vue` (lines 320-340)
**Severity:** HIGH

The dashboard Recent Activity section uses `activity.type` to check activity types for icons and labels. But the API (`getRecentActivity()`) returns `activity_type`, not `type`.

The recent activity feed shows correct summaries and dates but every entry says "Activity" instead of "Email", "Phone Call", "Meeting", etc., and shows the generic icon.

**Fix needed:** Change all `activity.type` references to `activity.activity_type` in the Recent Activity section.

---

### BUG-6: Dashboard Reviews Due Panel — Client Names Missing

**File:** `resources/js/views/Advisor/AdvisorDashboard.vue` (line 281)
**Severity:** HIGH

Dashboard bottom panel uses `review.client_name` but the API returns `review.display_name`. Also uses `review.review_type` which doesn't exist.

**Fix needed:** Change `review.client_name` to `review.display_name` and `review.review_type` to a formatted `review.review_frequency_months` value.

---

### BUG-7: Dashboard Client Table — "Last Communication" and "Last Report" Missing for Most Clients

**File:** `resources/js/views/Advisor/AdvisorDashboard.vue`
**Severity:** HIGH

The API returns `last_communication` and `last_report` as full `ClientActivity` objects. But the dashboard table renders these as `--` for clients where they exist. The template likely checks wrong field names. Example: James Carter has a suitability report in the seeded data but "Last Report" shows the raw `protection_review` text instead of a formatted label.

**Issues:**
- `last_report.report_type` is rendered raw (`protection_review`) instead of formatted ("Protection Review")
- `last_communication` shows `--` for some clients that DO have communications in the API response

---

## MEDIUM-SEVERITY ISSUES

### BUG-8: AdvisorClientSeeder — Returns Early When User Already Has is_advisor=true

**File:** `database/seeders/AdvisorClientSeeder.php` (lines 24-33)
**Severity:** MEDIUM (Fixed during this audit)

**Root Cause:** `DB::table('users')->where('email', '...')->update(['is_advisor' => true])` returns 0 when the column is already true (no rows modified). The seeder treated 0 as "user not found" and returned early.

**Fix applied:** Changed to first check if user exists, then update.

---

### BUG-9: Test Case Collisions — All 37 Advisor Pest Tests Fail to Run

**File:** Multiple test files
**Severity:** MEDIUM (Fixed during this audit)

All 5 advisor test files had redundant `uses(TestCase::class, RefreshDatabase::class)` calls that conflicted with `tests/Pest.php` global configuration. Running any advisor test or `--filter=Advisor` crashed Pest with `Test case already used` error.

**Fix applied:** Removed redundant `uses()` calls from all 4 test files that had them.

---

### BUG-10: No "Advisor" Link in Main App Sidebar Navigation

**File:** `resources/js/layouts/AppLayout.vue` or `resources/js/router/index.js`
**Severity:** MEDIUM

The main application sidebar has "Admin Panel" link for admin users but NO "Advisor" link for advisor users. Advisors must manually navigate to `/advisor` or know the URL. There's nothing discoverable in the UI.

**Fix needed:** Add an "Advisor Dashboard" link to the sidebar (alongside or below Admin Panel) that shows when `isAdvisor` is true.

---

### BUG-11: Missing `widow` Preview Persona (Margaret Thompson)

**File:** Database / `PreviewUserSeeder`
**Severity:** MEDIUM

The `widow` persona (Margaret Thompson) is not present in the preview users table (`preview_persona_id = 'widow'` not found). The `AdvisorClientSeeder` logs a warning and skips this persona. Expected 6 clients, got 5.

**Fix needed:** Verify `PreviewUserSeeder` includes the `widow` persona.

---

### BUG-12: Activity Log — Client Names Don't Show in Activity Entries

**File:** `resources/js/views/Advisor/AdvisorActivityLog.vue` (line 148)
**Severity:** MEDIUM

Even when BUG-2 is fixed, the template uses `activity.client_name` which doesn't exist on the raw `ClientActivity` model from the API. The API returns `activity.client` as a nested relationship. The violet badge for client name won't render.

**Fix needed:** Use `activity.client?.first_name + ' ' + activity.client?.surname` or add `client_name` to the `ClientActivityService` list response.

---

## LOW-SEVERITY ISSUES

### BUG-13: N+1 Query in AdvisorDashboardService

**File:** `app/Services/Advisor/AdvisorDashboardService.php` (lines 50, 88, 126)
**Severity:** LOW (performance)

`User::find($client->spouse_id)` is called in a loop for each client. For 10 clients with spouses, this causes 10 extra DB queries.

**Fix:** Eager-load spouse in the initial query via `with(['client.spouse'])` or batch-load all spouse IDs.

---

### BUG-14: Impersonation Middleware Not Applied to Any Route Group

**File:** `app/Http/Kernel.php`, `routes/api.php`
**Severity:** LOW

`AdvisorImpersonationMiddleware` is registered as `advisor.impersonate` middleware alias but is never applied to any route group. Impersonation state is stored in cache but never actually replaces the authenticated user on subsequent requests.

**Fix needed:** Apply `advisor.impersonate` middleware to the `auth:sanctum` middleware group, or to specific route groups where impersonation should take effect.

---

### BUG-15: Full Test Suite Has 23 Pre-existing Failures

**File:** `tests/Feature/TaxConfigurationTest.php`
**Severity:** LOW (not advisor-related)

Full Pest suite: 1990 passed, 23 failed. All failures are in `TaxConfigurationTest` due to `admin@fps.com` duplicate entry constraint violation — a test isolation issue where the test database already has this user from the seeder.

---

## FIELD NAME MISMATCH SUMMARY

| Vue Field | Correct API Field | Affected Components |
|-----------|-------------------|---------------------|
| `client_name` | `display_name` | ReviewsDue, Dashboard reviews, ActivityLog, Reports |
| `next_review_date` | `next_review_due` | ReviewsDue, ClientDetail |
| `review_frequency` / `review_type` | `review_frequency_months` (integer) | ReviewsDue, ClientDetail, Dashboard |
| `activity.type` | `activity.activity_type` | Dashboard recent activity |
| `report.acknowledged_date` | `report.report_acknowledged_date` | Reports |
| `report.client_name` | `report.client.first_name + surname` | Reports |

---

## WHAT WORKS

| Feature | Status | Notes |
|---------|--------|-------|
| Advisor Layout (top bar + sidebar) | WORKS | Correct branding, navigation, badges update |
| Dashboard Stats Cards | WORKS | 5 clients, 2 reviews, 0 comms, 2 reports |
| Dashboard Client Table | PARTIALLY WORKS | Names, initials, module dots, review dates show. Last comms/reports partially broken |
| All Clients Page | WORKS | 5 clients, search/filter UI present, module dots, action buttons |
| Advisor Middleware (auth gate) | WORKS | Non-advisors get 403 |
| All 11 API Endpoints | WORKS | All return 200 with correct data |
| Impersonation Enter/Exit API | WORKS | Cache correctly set/cleared, audit logged |
| Pest Tests (37 total) | ALL PASS | After test case collision fix |
| Seeder Data | WORKS | 5 clients, 19 activities (after seeder fix) |
| Router Guard | WORKS | `requiresAdvisor` meta correctly blocks non-advisors |

---

## FIX PRIORITY ORDER

1. **BUG-2 + BUG-3:** Fix pagination response parsing in ActivityLog and Reports (simple one-line fix each)
2. **BUG-4 + BUG-6:** Fix field name mismatches in ReviewsDue and Dashboard reviews (`client_name` → `display_name`, `next_review_date` → `next_review_due`)
3. **BUG-1:** Fix Client Detail page — transform API response to match expected shape
4. **BUG-5:** Fix dashboard Recent Activity type labels (`type` → `activity_type`)
5. **BUG-12:** Fix activity client name rendering
6. **BUG-10:** Add Advisor link to main sidebar
7. **BUG-7:** Fix Last Communication/Report rendering in dashboard table
8. **BUG-14:** Apply impersonation middleware to routes
