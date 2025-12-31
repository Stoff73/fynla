# Information Guide Feature

**Date:** 2025-12-31
**Commit:** 786e2d0

## Summary

Added a floating help button that shows users what data is needed for each module view, with plain-language explanations of why each piece of information is collected.

## Features

- **Floating Button**: Blue circular button (56px) fixed to bottom-right corner
- **Missing Items Badge**: Amber badge shows count of missing data items
- **Slide-out Panel**: Right-edge panel displays all requirements with status
- **Context-aware**: Automatically updates when navigating between modules
- **User Preference**: Toggle persists to database (except for preview users)

## User Experience

1. User navigates to a module (e.g., Protection, Retirement)
2. Floating button appears with badge showing missing data count
3. Clicking button opens panel showing:
   - All data requirements for current view
   - Status indicators (filled/missing)
   - Plain English explanations (no acronyms)
4. User can dismiss panel or toggle visibility preference

## Files Added

### Backend
- `app/Http/Controllers/Api/InfoGuideController.php` - API endpoints
- `app/Services/UserProfile/ModuleDataRequirementsService.php` - Requirements definitions
- `database/migrations/2025_12_30_164125_add_info_guide_enabled_to_users_table.php`

### Frontend
- `resources/js/components/Shared/InfoGuideButton.vue` - Floating button
- `resources/js/components/Shared/InfoGuidePanel.vue` - Slide-out panel
- `resources/js/store/modules/infoGuide.js` - Vuex store module

### Modified
- `resources/js/layouts/AppLayout.vue` - Added components
- `resources/js/router/index.js` - Module detection
- `resources/js/store/index.js` - Registered store module
- `routes/api.php` - API routes
- `app/Models/User.php` - Added info_guide_enabled field

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/info-guide/requirements?module=protection` | Get requirements for module |
| GET | `/api/info-guide/preference` | Get user's visibility preference |
| PUT | `/api/info-guide/preference` | Update visibility preference |

## Modules Supported

- Dashboard
- Protection
- Savings
- Investment
- Retirement
- Estate
- Net Worth

## Design Decisions

1. **Educational + Personalised**: Shows ALL data requirements (both filled and missing), not just missing items
2. **Plain English**: Uses user-friendly terms (e.g., "workplace pension" not "DC pension")
3. **Non-intrusive**: Small button, panel slides from edge
4. **Toggle Persists**: Registered users can hide the guide permanently
5. **Preview Mode**: Always visible for preview/demo users (preference not saved)
