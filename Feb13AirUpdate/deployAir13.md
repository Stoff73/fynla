# Deployment Notes - 13 February 2026 (Air)

### Status: Pending

### Rebuild Required: Yes
Frontend build already completed locally. Upload `public/build/` directory.

### PHP Files to Upload

```
app/Services/Onboarding/OnboardingService.php
app/Http/Controllers/Api/OnboardingController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Estate/WillController.php
routes/api.php
```

---

## 1. Skip to Dashboard + Areas to Complete Card

### Summary
Users can now exit onboarding at any point and go straight to the dashboard. A new "Areas to Complete" card appears on the dashboard showing skipped steps with links to the relevant module pages.

### What Changed

**OnboardingService.php**
- Added `skipToDashboard()` method - marks all uncompleted/unskipped steps as skipped and completes onboarding in one operation

**OnboardingController.php**
- Added `skipToDashboard()` controller method for the new endpoint

**routes/api.php**
- Added `POST /onboarding/skip-to-dashboard` route

**No change needed to PreviewWriteInterceptor** - existing `api/onboarding` exclusion already covers the new route via `str_starts_with` matching.

### Frontend Changes (included in build)

- `resources/js/services/onboardingService.js` - added `skipToDashboard()` API call
- `resources/js/store/modules/onboarding.js` - added `skipToDashboard` Vuex action
- `resources/js/components/Onboarding/SkipToDashboardModal.vue` (new) - confirmation modal
- `resources/js/components/Onboarding/OnboardingWizard.vue` - added skip link + modal
- `resources/js/components/Dashboard/AreasToCompleteCard.vue` (new) - dashboard card for skipped steps
- `resources/js/views/Dashboard.vue` - added AreasToCompleteCard as first card when user has skipped steps

---

## 2. Onboarding Validation Error Clearing Fix

### Summary
During onboarding, red validation error text below required fields now clears automatically when the user provides input, giving immediate clarity on what's still missing.

### What Changed

**PersonalInfoStep.vue**
- Added a deep watcher on `formData` that clears individual `fieldErrors` entries as the user fills in each required field
- Also clears the general error banner when all field errors are resolved

### Frontend Changes (included in build)

- `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` - added watcher to clear field errors on input

---

## 3. Net Worth Card Empty State

### Summary
The Net Worth dashboard card now always displays, even when users have no assets or liabilities. Shows £0 with a message prompting them to add assets and liabilities.

### What Changed

**Dashboard.vue**
- Removed `v-if="hasNetWorthData"` from the Net Worth DashboardCard so it always renders
- Moved existing data content into a `v-if="hasNetWorthData"` wrapper inside the card
- Added `v-else` empty state showing £0 in grey with message: "Add your assets and liabilities to see your net worth here."

### Frontend Changes (included in build)

- `resources/js/views/Dashboard.vue` - always show Net Worth card with empty state fallback

---

## 4. Estate Planning Card Empty State + Will Question

### Summary
The Estate Planning dashboard card now always displays, even when users have no data. Shows £0 with a message prompting them to add assets and liabilities. If the will question from onboarding hasn't been answered, the card shows inline Yes/No buttons to answer it directly on the dashboard.

### What Changed

**IHTController.php**
- Added `will_answered` field to `will_info` response to distinguish "never answered" from "answered no"

**Dashboard.vue**
- Removed `v-if="hasEstateData"` from the Estate Planning DashboardCard so it always renders
- Moved existing data content into a `v-if="hasEstateData"` wrapper inside the card
- Added `v-else` empty state showing £0 in grey with message
- Added inline will question with Yes/No buttons when `willAnswered` is false
- Will selection saves directly via `POST /estate/will` endpoint

**estate.js (Vuex store)**
- Added `willInfo` state, `setWillInfo` mutation
- Extracts `will_info` from IHT calculation responses
- Added `saveWill` action calling the existing will endpoint

**estateService.js**
- Added `saveWill()` API method

### Frontend Changes (included in build)

- `resources/js/views/Dashboard.vue` - always show estate card, empty state, will question
- `resources/js/store/modules/estate.js` - willInfo state management
- `resources/js/services/estateService.js` - saveWill API call

---

## 5. Fix £500k Phantom Estate Value on Will Tab

### Summary
The Valuable Information > Will tab showed a hardcoded £500,000 estate value for users with no assets. This was caused by a fallback in `WillController::calculateIntestacy()` that defaulted to £500k when estate value was 0. Now correctly shows £0 with a message to add assets.

### What Changed

**WillController.php**
- Removed hardcoded `$estateValue = 500000` fallback in `calculateIntestacy()` - now uses the actual estate value (0 for empty users)

**WillPlanning.vue**
- Added guard to only show `IntestacyRules` component when `netEstateValue > 0`
- Added empty state message when no estate data: "Add your assets and liabilities to see how your estate would be distributed under UK intestacy rules."

### Frontend Changes (included in build)

- `resources/js/components/Estate/WillPlanning.vue` - conditional intestacy display with empty state

---

### Post-Upload

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
