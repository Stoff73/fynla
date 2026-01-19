# Beta Registration Flow Changes - January 19, 2025

## Overview
Updates to the registration flow, beta warnings, and logo handling for the Fynla application.

## Changes Made

### 1. Beta Warning Messages
Added prominent beta warning banners to:
- **Login.vue** - Warning about beta status and data volatility
- **Register.vue** - Same warning message

Warning style: Vibrant amber background (`bg-amber-200 border-2 border-amber-500`) with warning icon.

### 2. Wishlist Link
Added "Wishlist for priority access on release" link with star icon to:
- Login page
- Register page
- Landing page

Links to Google Forms waitlist: `https://docs.google.com/forms/d/e/1FAIpQLSds1-zixuMDTjkBCZ3lEl-q5NzA0pwXyvb8cJIuNrz2fwjSXg/viewform`

### 3. Registration Flow Changes

#### Get Started Buttons
- Landing page "Get Started Free" buttons now open persona selection modal
- Header "Get Started" button navigates to `/?demo=true` which triggers the modal
- Users must now go through preview mode to register (no direct registration)

#### Removed from Login Page
- Removed "create a new account" link - registration only via preview mode

#### Post-Registration Flow
- **Removed**: KeepDataOrFreshModal (the "keep persona data or start fresh" choice)
- **All users** now go directly to onboarding welcome screen after registration
- Preview localStorage is cleared on registration completion

### 4. Onboarding Welcome Screen Updates
**File**: `resources/js/components/Onboarding/FocusAreaSelection.vue`

- Added Fynla logo (positioned to the right, inline with welcome text)
- Added "Skip onboarding and go to dashboard" button
- Logo uses transparent version for seamless blending

### 5. Transparent Logo Implementation

#### Background Removal
Used Python PIL to remove background from logo:
- Original backed up to `logo_backup.png`
- Flood-fill algorithm from edges with tolerance=60
- Created `logoTransparent.png` in `/logo/` folder

#### Site-wide Logo Update
Updated all logo imports to use `logoTransparent.png`:

| File | Path |
|------|------|
| FocusAreaSelection.vue | `@/assets/images/logoTransparent.png` |
| Register.vue | `@/assets/images/logoTransparent.png` |
| Login.vue | `@/assets/logoTransparent.png` |
| PublicLayout.vue | `@/assets/logoTransparent.png` |
| Navbar.vue | `@/assets/logoTransparent.png` |
| PrintHeader.vue | `@/assets/logoTransparent.png` |
| ComprehensiveProtectionPlan.vue | `@/assets/logoTransparent.png` |
| ComprehensiveEstatePlan.vue | `@/assets/logoTransparent.png` |
| InvestmentSavingsPlan.vue | `@/assets/logoTransparent.png` |

## Files Modified

### Vue Components
- `resources/js/views/Login.vue`
- `resources/js/views/Register.vue`
- `resources/js/views/Public/LandingPage.vue`
- `resources/js/layouts/PublicLayout.vue`
- `resources/js/components/Onboarding/FocusAreaSelection.vue`
- `resources/js/components/Navbar.vue`
- `resources/js/components/Common/PrintHeader.vue`
- `resources/js/views/Protection/ComprehensiveProtectionPlan.vue`
- `resources/js/views/Estate/ComprehensiveEstatePlan.vue`
- `resources/js/views/Plans/InvestmentSavingsPlan.vue`

### New Assets
- `logo/logoTransparent.png`
- `resources/js/assets/logoTransparent.png`
- `resources/js/assets/images/logoTransparent.png`
- `resources/js/assets/images/logo_backup.png` (original backup)

## Git Commits (betaInfo branch)
1. Beta warning messages on login/register
2. Wishlist links on login, register, landing pages
3. Get Started buttons open persona modal
4. Remove KeepDataOrFreshModal - all users go to onboarding
5. Add logo to onboarding welcome page
6. Move logo to right side inline with welcome text
7. Transparent logo site-wide implementation

## Testing Notes
- Register new user → should go directly to onboarding welcome screen
- No "keep data or fresh" modal should appear
- Logo should blend seamlessly with white backgrounds
- Beta warnings visible on login and register pages
- Wishlist links functional on all three pages
