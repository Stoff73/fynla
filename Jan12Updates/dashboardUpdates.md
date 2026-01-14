# Dashboard Updates - January 12, 2026

## Changes Made

### 1. IHT Message Styling Fix
**File:** `resources/js/components/Estate/EstateOverviewCard.vue`

Changed the IHT planning message from solid orange banner to bordered box style matching TaxOptimisationCard:
- **Before:** Solid amber background (`bg-amber-500`) with white text
- **After:** White background with amber border (`bg-white border-2 border-amber-500`) and amber text (`text-amber-700`)

Also updated the success message (no IHT liability) to use matching green bordered style.

### 2. Removed Plans Card
**File:** `resources/js/views/Dashboard.vue`

Removed the full-width Plans card from the bottom of the dashboard that contained links to:
- Protection Plan
- Estate Plan
- Investment & Savings Plan
- Retirement Plan (greyed out)
- Tax Plan (greyed out)
- Financial Plan (greyed out)

### 3. Draggable Dashboard Widgets
**Files:**
- `resources/js/views/Dashboard.vue` - Main implementation
- `app/Models/User.php` - Added cast
- `app/Http/Controllers/Api/UserProfileController.php` - API endpoint
- `routes/api.php` - Route
- `database/migrations/2026_01_12_115104_add_dashboard_widget_order_to_users.php` - Migration
- `package.json` - Added vuedraggable

**Features:**
- Users can drag widgets to reorder them on the dashboard
- Drag handle (grip icon) appears on hover
- Order is saved to database and persists across sessions
- Works on all devices (desktop and mobile touch)

**Technical Details:**
- Installed `vuedraggable@next` for Vue 3 drag-and-drop
- Added `dashboard_widget_order` JSON column to users table
- API endpoint: `PUT /api/user/dashboard-widget-order`
- Widget order stored as array of widget IDs

**Default Widget Order:**
1. net_worth
2. affordability
3. retirement
4. investment
5. tax
6. estate (conditional - only if IHT > 0)
7. protection (conditional - only if user has policies)
8. trusts (conditional - only if user has trusts)
9. admin_taxes (admin only)

## Migration Required
After pulling these changes, run:
```bash
php artisan migrate
```

## Commit
```
e71566f feat: Add draggable dashboard widgets and fix IHT message styling
```
