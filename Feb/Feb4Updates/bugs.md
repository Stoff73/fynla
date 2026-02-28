# Bug Report - Feb 4, 2025

## Goals Section: Life Events Not Showing on Production

### Status: ✅ RESOLVED

### Description

The Goals projection chart works correctly on localhost:8000 but fails to display life events on production (fynla.org). The chart loads but events/icons are not appearing above the projection bars.

### Environment

| Environment | Status |
|-------------|--------|
| localhost:8000 | ✅ Working |
| fynla.org | ❌ Events not showing |

### Symptoms

- Goals page loads on production
- Projection chart renders
- Life event icons do NOT appear above the bars
- Previously saw 500/503 errors on `/api/goals/dashboard-overview` (now resolved after file uploads)

### Root Cause Investigation

Initial diagnosis found that several new files were never uploaded to production, causing 500 errors. Files have now been uploaded and migrations run, but events still not displaying.

---

## Files Uploaded

### New PHP Files

| File | Status |
|------|--------|
| `app/Http/Controllers/Api/LifeEventController.php` | ✅ Uploaded |
| `app/Http/Requests/StoreLifeEventRequest.php` | ✅ Uploaded |
| `app/Http/Requests/UpdateLifeEventRequest.php` | ✅ Uploaded |
| `app/Models/LifeEvent.php` | ✅ Uploaded |
| `app/Services/Goals/LifeEventService.php` | ✅ Uploaded |

### Modified PHP Files

| File | Status |
|------|--------|
| `app/Http/Controllers/Api/GoalsController.php` | ✅ Uploaded |
| `app/Models/Goal.php` | ✅ Uploaded |
| `routes/api.php` | ✅ Uploaded |
| `app/Services/Goals/GoalsProjectionService.php` | ✅ Uploaded |

### Migration Files

| File | Status |
|------|--------|
| `database/migrations/2026_02_03_100001_add_charity_fields_to_bequests_table.php` | ✅ Uploaded |
| `database/migrations/2026_02_03_100002_add_estate_planning_to_user_assumptions_table.php` | ✅ Uploaded |
| `database/migrations/2026_02_03_120001_create_life_events_table.php` | ✅ Uploaded |
| `database/migrations/2026_02_03_120002_add_projection_fields_to_goals_table.php` | ✅ Uploaded |

### Frontend Build

| Item | Status |
|------|--------|
| `public/build/` directory | ✅ Uploaded (rebuilt Feb 4) |

---

## Fixes Attempted

### 1. File Upload (Complete)

All missing PHP files uploaded via SiteGround File Manager.

### 2. Database Migrations

```bash
php artisan migrate --force
```

**Result:** ✅ Migrations ran successfully

### 3. Cache Clear & Rebuild

```bash
php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache
```

**Result:** ✅ Caches cleared and rebuilt

### 4. Frontend Rebuild

```bash
./deploy/fynla-org/build.sh
```

**Result:** ✅ Build completed, uploaded to production

---

## Next Steps to Investigate

1. **Check API response** - Verify `/api/goals/dashboard-overview` returns events data
2. **Check life_events table** - Confirm table was created and has data for preview users
3. **Check browser console** - Look for JavaScript errors on production
4. **Check network tab** - Verify API calls return expected data structure
5. **Compare API responses** - Diff localhost vs production responses
6. **Check GoalsProjectionService** - Verify it's returning events in the projection data
7. **Reseed preview users** - May need to run `php artisan db:seed --class=PreviewUserSeeder --force` to populate life events

---

## Resolution

**Root Cause:** The `life_events` table was empty on production. The PreviewUserSeeder had been updated to seed life events from persona JSON files, but the seeder hadn't been run on production after the new migrations.

**Fix Applied:**
```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

This populated the `life_events` table with events from all persona files (car purchases, home moves, inheritances, business sales, etc.).

**Date Resolved:** Feb 4, 2025

---

## Related Files

### Frontend Components

- `resources/js/components/Goals/GoalsProjectionChart.vue` - Main chart component
- `resources/js/components/Goals/EventIcon.vue` - Event icon component
- `resources/js/constants/eventIconSvgs.js` - SVG icon definitions

### Backend Services

- `app/Services/Goals/GoalsProjectionService.php` - Projection calculations
- `app/Services/Goals/LifeEventService.php` - Life event CRUD operations

### API Endpoints

- `GET /api/goals/dashboard-overview` - Returns goals with projection data
- `GET /api/life-events` - Returns user's life events
