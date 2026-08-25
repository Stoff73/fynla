---
tags:
  - april-2026
  - deploy
  - bug-fix
date: 2026-04-09
---

# Deploy Guide — Session 49 Fixes

Back to [[April Index]]

## Summary

7 fixes across preview spouse toggle, dashboard data refresh, Fyn AI markdown, employment status enum, AI tool definitions, plan tier gating, and estate route access.

## Changes

### 1. Preview Spouse Toggle Stuck (PreviewBanner.vue)

`switchingSpouse` and `switching` flags were only reset in `catch` blocks. Since the switch succeeds without error, the flags stayed `true` permanently. SPA navigation reuses the layout component, so the next click was silently blocked by `if (this.switchingSpouse) return`.

**Fix:** Moved flag resets to `finally` blocks.

### 2. Dashboard Data Not Refreshing on Persona Switch (Dashboard.vue)

After toggling to spouse view, the dashboard showed the correct name but stale data from the previous user. `loadAllData()` was guarded by a `dataLoaded` flag set once on mount and never reset.

**Fix:** Added `user.id` change detection in the `currentUser` watcher. When the user ID changes (persona switch), `loadAllData()` fires again.

### 3. Fyn AI Markdown Headings Not Rendered (AiMessageContent.vue)

`##` and `###` headings from Fyn AI appeared as raw text. Bold, italic, and lists were already handled.

**Fix:** Added heading regex replacements before bold/italic parsing. `###` renders as `h4`, `##` renders as `h3`, both styled with `font-bold text-horizon-500`.

### 4. Employment Status Enum Missing `full_time` (Migration)

The `users.employment_status` enum only had `employed`, `part_time`, `self_employed`, `retired`, `unemployed`, `other`. Fyn AI and users saying "full time" caused MySQL to silently truncate the value, losing the entire profile save. Production logs confirmed: `Data truncated for column 'employment_status'`.

**Fix:** New migration adds `full_time` to the enum. **Must run on production.**

### 5. AI Tool Definitions Missing Valid Enum Values (AiToolDefinitions.php, XaiToolDefinitions.php)

Both tool definition files listed incomplete `employment_status` options, so the AI model could freely invent values like `full_time` or `contractor` that MySQL rejects.

**Fix:** Updated both files to list all valid enum values: `employed/full_time/part_time/self_employed/retired/unemployed/other`.

### 6. Family Tab Gated by Plan Tier (UserProfile.vue)

The Family tab in user profile was visible to all plan tiers. It should only be visible to Family and Pro plan users.

**Fix:** Added plan-based tab filtering. The Family tab has `requiredPlan: 'family'`, and a computed `tabs` filters based on the user's subscription plan. Trial and preview users see all tabs.

### 7. Liabilities and Estate Route Gating (api.php)

Liabilities CRUD was inside the `feature:pro` estate route group, blocking Standard and Family plan users. The estate read-only endpoints (GET index, IHT calculation, net worth, cash flow) were also Pro-gated, breaking dashboard IHT cards for all lower tiers.

**Fix:**
- Extracted liability CRUD routes to their own `feature:standard` group
- Extracted estate read-only + IHT calculation routes with no tier gate (all authenticated users)
- Estate write operations (assets, gifts, trusts, will, LPA) remain at `feature:pro`

## Files Changed

### Backend (PHP — upload to server)

| File | Change |
|------|--------|
| `routes/api.php` | Liability routes extracted to `feature:standard`, estate read-only ungated, estate writes stay `feature:pro` |
| `app/Services/AI/AiToolDefinitions.php` | Added all valid `employment_status` enum values to tool description |
| `app/Services/AI/XaiToolDefinitions.php` | Added all valid `employment_status` enum values to tool description |
| `database/migrations/2026_04_09_120000_add_full_time_to_employment_status_enum.php` | New migration: adds `full_time` to employment_status enum |

### Frontend (build assets only)

| File | Change |
|------|--------|
| `resources/js/components/Preview/PreviewBanner.vue` | `switchingSpouse`/`switching` reset in `finally` |
| `resources/js/components/Shared/AiMessageContent.vue` | `##`/`###` heading rendering |
| `resources/js/views/Dashboard.vue` | Reload data on `user.id` change |
| `resources/js/views/UserProfile.vue` | Family tab gated to `family`+ plan |

## Upload Steps

### 1. Build frontend assets locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload build assets

Upload `public/build/` to:
```
~/www/fynla.org/public_html/public/build/
```

### 3. Upload PHP files

```
routes/api.php
app/Services/AI/AiToolDefinitions.php
app/Services/AI/XaiToolDefinitions.php
database/migrations/2026_04_09_120000_add_full_time_to_employment_status_enum.php
```

### 4. SSH and run migration + clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Testing

1. Go to landing page, select Carter persona, toggle James to Emily and back — data should refresh each time
2. Go to landing page, select Mitchell persona, toggle David to Sarah and back — same
3. Ask Fyn a question with `###` headings in response — should render as styled headings
4. Log in as a Family plan user — Family tab should be visible in profile, Liabilities page should work
5. Log in as a Standard plan user — Family tab should NOT be visible in profile, Liabilities should work
6. Log in as a Student plan user — Family tab NOT visible, Liabilities greyed out in sidebar
