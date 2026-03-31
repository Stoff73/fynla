# Journey Link — Stage CTA to Onboarding Map

**Date:** 31 March 2026
**Branch:** `journeyLink`
**Status:** DEPLOYED — production tested 31 March 2026

---

## What It Does

Stage pages (`/stage/*`) now link directly to registration with a journey context. After registration, the user lands on the journey map for the stage they came from, skipping the stage selection screen.

**Flow:** `/stage/building-foundations` → "Start your journey" CTA → `/register?stage=early_career` → register → verify → `/onboarding?stage=early_career` → journey map shown → "Start My Journey" → onboarding steps

---

## Stage-to-Config Mapping

| Stage Page | URL Slug | Config ID | Map Title |
|-----------|---------|-----------|-----------|
| Starting Out | `/stage/starting-out` | `university` | Starting Out |
| Building Foundations | `/stage/building-foundations` | `early_career` | Building Foundations |
| Protecting and Growing | `/stage/protecting-and-growing` | `mid_career` | Protecting What Matters |
| Planning Your Future | `/stage/planning-your-future` | `peak` | Planning Your Future |
| Enjoying Your Wealth | `/stage/enjoying-your-wealth` | `retirement` | Enjoying Your Wealth |

---

## Security Fix — Data Leakage Between Users

**Problem found during testing:** When a user was logged in (e.g. John Smith) and then registered a new account, the new user's onboarding forms showed John's date of birth, gender, and marital status. The dashboard after clicking "Exit" also showed John's data.

**Root cause:** Two issues in `auth.js`:
1. The `register` and `login` actions only reset `userProfile` Vuex state, but NOT `lifeStage`, `onboarding`, or `netWorth` — stale data persisted across user switches.
2. The stored auth token was only cleared for preview users (`wasInPreviewMode` check), NOT for regular users — the old user's session token leaked into the new user's session.

**Fix:**
- `register` and `login` actions now reset ALL module states: `userProfile`, `lifeStage`, `onboarding`, `netWorth`
- `removeToken()` is now called ALWAYS before registration/login, not just for preview users

---

## Files Changed

| File | Change |
|------|--------|
| `resources/js/views/Public/stages/StartingOutPage.vue` | CTA → `/register?stage=university`, text "Start your journey" |
| `resources/js/views/Public/stages/BuildingFoundationsPage.vue` | CTA → `/register?stage=early_career`, text "Start your journey" |
| `resources/js/views/Public/stages/ProtectingAndGrowingPage.vue` | CTA → `/register?stage=mid_career`, text "Start your journey" |
| `resources/js/views/Public/stages/PlanningYourFuturePage.vue` | CTA → `/register?stage=peak`, text "Start your journey" |
| `resources/js/views/Public/stages/EnjoyingYourWealthPage.vue` | CTA → `/register?stage=retirement`, text "Start your journey" |
| `resources/js/views/Register.vue` | Forwards `?stage=` param to onboarding redirect |
| `resources/js/components/Onboarding/OnboardingWizard.vue` | Shows journey map (FocusAreaSelection STATE 2) when `?stage=` present |
| `resources/js/components/Onboarding/FocusAreaSelection.vue` | Auto-selects stage from `?stage=` query on mount |
| `resources/js/store/modules/auth.js` | Reset all Vuex stores + always clear token on register/login |

## No Database Changes

No migrations, no model changes, no backend changes. Frontend only.

---

## Testing

- Logged in as John → signed out → clicked "Start your journey" on Starting Out → registered new user → journey map showed "Starting Out" with correct 6 steps → clicked "Start My Journey" → Personal Info form had only registration data (name, email), all other fields empty
- Tested stage ID corrections: `mid_career` (not `family_building`), `peak` (not `peak_earning`)
- CTA text updated to "Start your journey" on all 5 stage pages

### Production Testing
- All 5 CTAs verified on fynla.org with correct stage params and "Start your journey" text
- Full end-to-end: `/stage/protecting-and-growing` → "Start your journey" → register as prodtest@fynla.org → verify code → `/onboarding?stage=mid_career` → journey map showed "Protecting What Matters" with 9 steps
- Test user cleaned up after testing
