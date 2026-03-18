# Advisor Dashboard Fixes — Deployment Guide

**Date:** 18 March 2026
**Commits:** `5648d47`, `16d1597`, `82e709a`
**Depends on:** Original advisor PR #136 already merged to main

---

## Rebuild Required: YES

Frontend files changed — **must run build script** before uploading.

```bash
./deploy/fynla-org/build.sh
```

---

## Post-Upload Commands (run on server via SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear all caches (required — Kernel.php middleware stack changed)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

No new migrations needed. No new seeders needed (seeder fix is for local dev only).

---

## Files to Upload

### Modified PHP Files

| File | What Changed |
|------|-------------|
| `app/Http/Controllers/Api/AdvisorController.php` | `clientDetail()` now returns flat transformed response with `display_name`, `module_status`, `activities` array instead of raw Eloquent model |
| `app/Services/Advisor/ClientActivityService.php` | `listForAdvisor()` now adds `client_name` string to each activity in paginated response |
| `app/Http/Kernel.php` | Added `AdvisorImpersonationMiddleware` to `api` middleware group (line 49) — **CRITICAL: without this, impersonation does not work** |
| `app/Http/Middleware/AdvisorImpersonationMiddleware.php` | Added skip for `/api/advisor/*` routes so advisor retains identity on advisor endpoints |
| `database/seeders/AdvisorClientSeeder.php` | Fixed early return when `is_advisor` already true (local dev only, but upload for consistency) |

### Frontend Files (compiled into `public/build/`)

Upload the entire `public/build/` directory after running the build script.

| Source File | What Changed |
|-------------|-------------|
| `resources/js/views/Advisor/AdvisorDashboard.vue` | Fixed `activity.type` → `activity_type`, `review.client_name` → `display_name`, added `formatReviewFrequency()`, `formatReportType()` |
| `resources/js/views/Advisor/AdvisorReviewsDue.vue` | Fixed `client_name` → `display_name`, `next_review_date` → `next_review_due`, `review_frequency` → `review_frequency_months`, updated `formatFrequency()` for integers |
| `resources/js/views/Advisor/AdvisorClientDetail.vue` | Fixed field names, `formatFrequency()` for integers, `isReviewOverdue` computed, activities pagination parsing |
| `resources/js/views/Advisor/AdvisorActivityLog.vue` | Fixed pagination response double-unwrapping (`data.data` → `response.data.data`) |
| `resources/js/views/Advisor/AdvisorReports.vue` | Fixed pagination unwrapping, `acknowledged_date` → `report_acknowledged_date`, added `formatReportType()` |
| `resources/js/components/SideMenu.vue` | Added "Advisor Dashboard" link in sidebar for advisor users |
| `resources/js/components/Navbar.vue` | Added violet "Advisor" button in top navbar for advisor users |
| `resources/js/layouts/AdvisorLayout.vue` | Replaced text "fynla" with `LogoHiResFynlaLight.png` image |

### Files NOT to Upload (test files)

- `tests/Feature/Api/AdvisorControllerTest.php`
- `tests/Feature/Middleware/AdvisorMiddlewareTest.php`
- `tests/Unit/Services/Advisor/AdvisorDashboardServiceTest.php`
- `tests/Unit/Services/Advisor/AdvisorImpersonationServiceTest.php`

---

## Upload Checklist

1. Build locally: `./deploy/fynla-org/build.sh`
2. Upload `public/build/` directory to server
3. Upload the 5 **Modified PHP Files** listed above
4. SSH to server and run cache clear + optimize commands
5. Test: login as chris@fynla.org → click "Advisor" in top navbar → verify dashboard loads with clients
6. Test impersonation: click "Enter Profile" on a client → verify their financial data shows → click "Exit" → verify return to advisor dashboard

---

## What These Fixes Solve

| Before | After |
|--------|-------|
| Client Detail page: blank white screen | Full client profile with modules, review info, activity timeline |
| Activity Log: "No activities found" | All 19+ activities with type labels and client name badges |
| Suitability Reports: "No reports" | All reports with formatted types and dates |
| Reviews Due: missing client names, "--" for dates | Full client names, correct dates and frequencies |
| Dashboard Recent Activity: all show "Activity" | Correct labels: "Email sent", "Phone call", "Suitability report sent" |
| Dashboard Reviews panel: empty client names | Client names showing correctly |
| Impersonation: showed advisor's empty dashboard | Shows the actual client's financial data |
| No way to reach advisor dashboard from main app | "Advisor" button in top navbar + "Advisor Dashboard" in sidebar |
| Text "fynla" in advisor top bar | Proper Fynla logo image |
