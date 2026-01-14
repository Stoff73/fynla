# Narrative-Driven Onboarding System Implementation Plan

## Overview

Transform the Fynla onboarding experience from data entry to self-discovery. Users will choose between a Progressive Dashboard (explore at own pace) or Guided Onboarding (narrative five-act journey). The dashboard adapts based on age, life stage, and life events.

## Key Changes Summary

1. **Remove persona data copying** - Delete KeepDataOrFreshModal functionality
2. **New registration flow** - Choice between Progressive Dashboard or Guided Onboarding
3. **Module-specific narrative onboarding** - Full chapter experience when entering each module
4. **Life stage identity capture** - Ask during onboarding, refine from data
5. **Dashboard adaptation** - Age-based + life event triggers
6. **Settings toggle** - Enable/disable onboarding prompts

---

## Phase 1: Database Schema

### 1.1 Migration: User Onboarding Fields

**File:** `database/migrations/2026_01_13_000001_add_narrative_onboarding_to_users.php`

Add to `users` table:
```php
$table->boolean('onboarding_enabled')->default(true);
$table->enum('onboarding_choice', ['progressive', 'guided'])->nullable();
$table->string('life_stage', 50)->nullable();
// Values: building_stability, growing_wealth, protecting_family, planning_freedom, running_business
$table->json('module_onboarding_status')->nullable();
// Structure: {"protection": {"completed": true, "completed_at": "..."}, ...}
$table->json('dashboard_emphasis')->nullable();
```

### 1.2 Migration: Life Events Table

**File:** `database/migrations/2026_01_13_000002_create_life_events_table.php`

```php
Schema::create('life_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('event_type', [
        'marriage', 'divorce', 'child_birth', 'child_adopted',
        'bereavement', 'inheritance', 'home_purchase', 'home_sale',
        'job_change', 'retirement', 'business_started', 'business_sold',
        'health_change', 'other'
    ]);
    $table->date('event_date');
    $table->text('notes')->nullable();
    $table->json('related_data')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'event_type']);
});
```

---

## Phase 2: Backend Services

### 2.1 Update User Model

**File:** `app/Models/User.php`

Add to `$casts`:
```php
'onboarding_enabled' => 'boolean',
'module_onboarding_status' => 'array',
'dashboard_emphasis' => 'array',
```

### 2.2 New Model: LifeEvent

**File:** `app/Models/LifeEvent.php`

### 2.3 New Services

| Service | File | Purpose |
|---------|------|---------|
| NarrativeOnboardingService | `app/Services/Onboarding/NarrativeOnboardingService.php` | Orchestrates five-act narrative for each module |
| LifeStageService | `app/Services/Onboarding/LifeStageService.php` | Infers life stage, calculates dashboard emphasis |
| LifeEventService | `app/Services/Onboarding/LifeEventService.php` | Records events, triggers recalculations |
| DashboardAdaptationService | `app/Services/Dashboard/DashboardAdaptationService.php` | Returns adapted dashboard config |

**Key method - LifeStageService::inferLifeStage():**
```php
// Inference logic based on:
// - Age (from date_of_birth)
// - Has children (familyMembers relationship)
// - Has property (properties relationship)
// - Has business (businessInterests relationship)
// Returns: building_stability | growing_wealth | protecting_family | planning_freedom | running_business
```

**Key method - DashboardAdaptationService::getAgeBasedEmphasis():**
```php
if ($age < 35) return ['primary' => ['savings', 'protection'], 'secondary' => ['investment']];
elseif ($age < 50) return ['primary' => ['investment', 'retirement'], 'secondary' => ['protection']];
elseif ($age < 60) return ['primary' => ['retirement', 'estate'], 'secondary' => ['investment']];
else return ['primary' => ['estate', 'retirement'], 'secondary' => ['protection']];
```

### 2.4 New Controllers

| Controller | Endpoints |
|-----------|-----------|
| NarrativeOnboardingController | `POST /onboarding/choice`, `POST /onboarding/life-stage`, `GET /onboarding/module/{module}/narrative`, `POST /onboarding/module/{module}/complete` |
| LifeEventController | `GET /life-events`, `POST /life-events`, `DELETE /life-events/{id}` |
| DashboardAdaptationController | `GET /dashboard/adaptation` |

---

## Phase 3: Frontend - Registration Flow

### 3.1 Remove Persona Data Copying

**File to delete:** `resources/js/components/Preview/KeepDataOrFreshModal.vue`

**Update:** `resources/js/views/Register.vue`

Remove:
- Import of KeepDataOrFreshModal (line 191)
- Component registration (lines 200-203)
- Template usage (lines 178-184)
- handleKeepDataChoice function (lines 337-366)
- showKeepDataModal state and related logic

Update `completeRegistration()`:
```javascript
const completeRegistration = async (data) => {
  authService.setToken(data.access_token);
  store.commit('auth/setToken', data.access_token);
  store.commit('auth/setUser', data.user);

  // Clear preview state
  localStorage.removeItem('preview_persona_id');
  localStorage.removeItem('preview_mode');

  // Redirect to onboarding choice (NEW)
  router.push({ name: 'OnboardingChoice' });
};
```

### 3.2 New: Onboarding Choice View

**File:** `resources/js/views/Onboarding/OnboardingChoiceView.vue`

Two options:
1. **"Explore at Your Own Pace"** (progressive) - Dashboard grows with data
2. **"Guided Journey"** (guided) - Narrative five-act experience

### 3.3 New: Life Stage Selector

**File:** `resources/js/components/Onboarding/LifeStageSelector.vue`

Five life stages (from onboard.md):
- Building Stability - Getting established, building foundations
- Growing Wealth - Accumulating assets, increasing income
- Protecting Family - Securing loved ones' futures
- Planning Freedom - Preparing for retirement and independence
- Running a Business - Building and protecting business wealth

---

## Phase 4: Frontend - Module Onboarding

### 4.1 Module Onboarding Wrapper

**File:** `resources/js/components/Onboarding/ModuleOnboarding/ModuleOnboardingWrapper.vue`

Full-screen modal with progress indicator showing five acts. Handles:
- Fetching narrative content for module
- Tracking current act
- Intelligent field skipping (checks existing data before showing questions)

### 4.2 Act Components

**Directory:** `resources/js/components/Onboarding/ModuleOnboarding/Acts/`

| Act | File | Purpose | Emotion |
|-----|------|---------|---------|
| Identity | ActIdentity.vue | "This is about me" - establish relevance | Curiosity |
| Reality | ActReality.vue | Capture current state without judgement | Relief |
| Stakes | ActStakes.vue | Show why clarity matters | Motivation |
| Vision | ActVision.vue | Anchor in aspiration | Hope |
| First Win | ActFirstWin.vue | Deliver immediate value | Confidence |

### 4.3 Intelligent Field Skipping

Before showing a question, check if data already exists:
```javascript
// In ActReality.vue
const skipField = (field) => {
  const existingData = props.existingData;
  return existingData[field] !== null && existingData[field] !== undefined;
};
```

API endpoint: `GET /api/onboarding/module/{module}/skip-fields`

---

## Phase 5: Frontend - Progressive Dashboard

### 5.1 Update Dashboard.vue

**File:** `resources/js/views/Dashboard.vue`

Add:
1. Welcome card for new users with no data
2. Progressive reveal logic (cards appear as data is added)
3. Module onboarding trigger when user clicks "Add" on empty module

```vue
<template>
  <!-- Welcome card for users with no data -->
  <WelcomeDashboardCard
    v-if="isNewUser && !hasAnyData"
    @select-module="triggerModuleOnboarding"
  />

  <!-- Cards with conditional visibility based on data presence -->
  <NetWorthOverviewCard v-if="shouldShowCard('netWorth')" />
  <RetirementOverviewCard v-if="shouldShowCard('retirement')" />
  <!-- etc... -->

  <!-- Module onboarding modal -->
  <ModuleOnboardingWrapper
    v-if="activeModuleOnboarding"
    :module="activeModuleOnboarding"
    @complete="handleModuleOnboardingComplete"
  />
</template>
```

### 5.2 New: Welcome Dashboard Card

**File:** `resources/js/components/Dashboard/WelcomeDashboardCard.vue`

Shows module selection grid for first-time users. Each module has icon, label, and brief description.

### 5.3 New: Vuex Store - dashboardAdaptation

**File:** `resources/js/store/modules/dashboardAdaptation.js`

```javascript
state: {
  cardOrder: [],
  primaryModules: [],
  secondaryModules: [],
  moduleDataPresence: {}, // { protection: true, savings: false, ... }
}

getters: {
  shouldShowCard: (state) => (module) => {
    return state.moduleDataPresence[module] || state.primaryModules.includes(module);
  },
  emptyModules: (state) => {
    return Object.keys(state.moduleDataPresence).filter(m => !state.moduleDataPresence[m]);
  }
}
```

---

## Phase 6: Life Events System

### 6.1 Life Events Panel

**File:** `resources/js/components/Profile/LifeEventsPanel.vue`

- List of user's recorded life events
- Add event button -> modal
- Delete event functionality

### 6.2 Life Event Modal

**File:** `resources/js/components/Profile/LifeEventModal.vue`

Form fields:
- Event type (dropdown)
- Event date
- Notes (optional)
- Related data (conditional based on event type, e.g., inheritance amount)

### 6.3 Event Type Implications

| Event | Dashboard Impact |
|-------|------------------|
| Marriage | Add Estate emphasis (spouse exemption), Protection (spouse cover) |
| Child Birth | Add Protection emphasis, update Estate RNRB |
| Inheritance | Add Estate/Investment emphasis, prompt IHT review |
| Home Purchase | Add Property, prompt mortgage protection |
| Retirement | Major restructure - Retirement and Estate primary |

---

## Phase 7: Settings Toggle

### 7.1 Update Settings.vue

**File:** `resources/js/views/Settings.vue`

Add toggle following existing `info_guide_enabled` pattern:

```vue
<div class="flex items-center justify-between py-4 border-b">
  <div>
    <h3 class="text-body-base font-medium">Guided Onboarding</h3>
    <p class="text-body-sm text-gray-600">
      Show narrative introductions when exploring new modules
    </p>
  </div>
  <label class="relative inline-flex items-center cursor-pointer">
    <input type="checkbox" v-model="onboardingEnabled" @change="updateOnboardingPreference" class="sr-only peer" />
    <div class="toggle-switch peer-checked:bg-primary-600"></div>
  </label>
</div>
```

---

## Phase 8: Routing

### 8.1 New Routes

**File:** `resources/js/router/index.js`

```javascript
{ path: '/welcome', name: 'OnboardingChoice', component: OnboardingChoiceView, meta: { requiresAuth: true, hideNavbar: true } },
{ path: '/welcome/life-stage', name: 'LifeStageSelection', component: LifeStageSelectionView, meta: { requiresAuth: true, hideNavbar: true } },
{ path: '/profile/life-events', name: 'LifeEvents', component: LifeEventsView, meta: { requiresAuth: true } },
```

### 8.2 Navigation Guard Update

Redirect new users (no `onboarding_choice` set) to OnboardingChoice:

```javascript
router.beforeEach(async (to, from, next) => {
  const user = store.getters['auth/currentUser'];
  if (user && !user.onboarding_choice && to.meta.requiresAuth && to.name !== 'OnboardingChoice') {
    next({ name: 'OnboardingChoice' });
    return;
  }
  next();
});
```

---

## Critical Files Summary

| File | Action |
|------|--------|
| `resources/js/views/Register.vue` | Remove KeepDataOrFreshModal, redirect to OnboardingChoice |
| `resources/js/components/Preview/KeepDataOrFreshModal.vue` | DELETE |
| `app/Models/User.php` | Add new casts for onboarding fields |
| `resources/js/views/Dashboard.vue` | Add progressive reveal, module onboarding trigger |
| `resources/js/router/index.js` | Add new routes, update navigation guard |
| `resources/js/views/Settings.vue` | Add onboarding toggle |

---

## Verification Plan

### Backend Testing
```bash
# After migrations
php artisan migrate
php artisan db:seed --class=TaxConfigurationSeeder --force

# Test API endpoints
curl -X POST "http://localhost:8000/api/preview/login/young_family" -H "Accept: application/json"
# Use token for:
curl -X POST "/api/onboarding/choice" -d '{"choice": "progressive"}'
curl -X POST "/api/onboarding/life-stage" -d '{"life_stage": "protecting_family"}'
curl -X GET "/api/dashboard/adaptation"
```

### Frontend Testing
1. Register new account -> should redirect to /welcome
2. Select "Progressive Dashboard" -> should see minimal dashboard with welcome card
3. Click module to add data -> should trigger module onboarding
4. Complete module onboarding -> data captured, dashboard card appears
5. Check Settings -> onboarding toggle should work
6. Log life event -> dashboard emphasis should update

### Pest Tests
- `tests/Unit/Services/Onboarding/LifeStageServiceTest.php`
- `tests/Unit/Services/Dashboard/DashboardAdaptationServiceTest.php`
- `tests/Feature/Api/NarrativeOnboardingTest.php`
- `tests/Feature/Api/LifeEventTest.php`

---

## Implementation Order

1. Database migrations (Phase 1)
2. Backend services and controllers (Phase 2)
3. Remove persona copying, add OnboardingChoice view (Phase 3)
4. Module onboarding components (Phase 4)
5. Progressive dashboard updates (Phase 5)
6. Life events system (Phase 6)
7. Settings toggle (Phase 7)
8. Routing updates (Phase 8)
9. Testing and refinement

---

## Reference Documents

- `/onboard/onboard.md` - Narrative onboarding philosophy (five-act structure)
- `/onboard/compass_artifact_*.md` - UK financial planning segmentation data
