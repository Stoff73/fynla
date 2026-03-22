# AI Form Fill — Current State Assessment

**Date:** 22 March 2026
**AI Form Fill Branch:** `aiFormFill` — MERGED to main (PR #156, 24 commits)
**Onboarding Branch:** `onboardingUpdates` (21 commits, in progress)
**Status:** AI form fill: savings, investments, protection, pensions, liabilities working. Onboarding: journey resumption, clickable steps, multiple executors, will overview, goals skip modal, asset tab form closing, NS&I field hiding, useful resources sidebar card, required field asterisks.

---

## Architecture Summary

When a user tells Fyn to create a record, the AI no longer saves directly to the database. Instead:

1. **Backend** (`CoordinatingAgent`) returns `{ action: 'fill_form', entity_type, route, fields }` instead of `Model::create()`
2. **SSE** (`HasAiChat.php`) yields a `fill_form` event to the frontend
3. **Frontend** (`aiChat.js`) handles the event: navigates to the page, then dispatches `aiFormFill/startFill` after 500ms
4. **Page component** watcher (e.g. `CashOverview.vue`) detects `pendingFill` and opens the modal
5. **Form component** watcher (e.g. `SaveAccountModal.vue`) starts the field sequence — each field highlights with violet ring for 250ms, value is set, then moves to the next field
6. **Auto-submit** — after all fields filled, `handleSubmit()` fires automatically after 250ms delay
7. **Confirmation** — parent component calls `completeFill` which adds a "Done" message to chat

**Key files:**
- `resources/js/store/modules/aiFormFill.js` — coordination store (pendingFill, field sequencing, highlighting)
- `resources/js/store/modules/aiChat.js` — handles `fill_form` SSE event (line 324)
- `app/Agents/CoordinatingAgent.php` — all 16 `handleCreate*` methods return `fill_form`
- `app/Traits/HasAiChat.php` — yields `fill_form` SSE event (line 212)
- `resources/css/app.css` — `.ai-fill-highlight` class (violet ring + bg)

---

## What Works (Browser Verified)

### Savings Account — WORKING (institution fix commit `4a1b646`)
- **Test:** "Add my Lloyds easy access savings with £12,000 at 3.1% interest"
- **Result:** Navigates to /net-worth/cash → modal opens → institution "Lloyds" visible in form → fields fill (account_type: easy_access, balance: 12000, rate: 3.1) → auto-submits → account saved to DB with institution "Lloyds" → card shows "Lloyds / £12,000" → chat confirms
- **DB verification:** institution=Lloyds, account_type=easy_access, current_balance=12000.00, interest_rate=3.1
- **Previously broken:** institution was null — same pattern as protection/pension, field set during highlight sequence didn't persist. Fixed by pre-setting institution and account_type in pendingFill handler before field sequence starts

### Investment Account — WORKING
- **Test:** "I have a Hargreaves Lansdown stocks and shares ISA worth £30,000"
- **Result:** Navigates to /net-worth/investments → modal opens → fields fill (type: ISA, provider: Hargreaves Lansdown, value: 30000) → auto-submits → account in DB → Monte Carlo projection running → chat confirms
- **Previously broken:** 422 validation error because `account_type` was `stocks_shares_isa` (AI value) instead of `isa` (form value), and `provider` was null because AI put name in `account_name`
- **Fixed in commit `5eca8c7`:** Added `match()` to map AI account types to form values, added `account_name` → `provider` fallback

### Protection Policy — WORKING (fixed commit `41022cc`)
- **Test:** "Add my Aviva level term life insurance for £250,000 at £25 per month for 20 years"
- **Result:** Modal opens on /protection → fields fill (policyType: life, life_policy_type: level_term, provider: Aviva, coverage_amount: 250000, premium: 25, term: 20) → auto-submits → policy saved to DB → full gap analysis renders → chat confirms
- **DB verification:** `life_insurance_policies` record: policy_type=level_term, provider=Aviva, sum_assured=250000.00, premium_amount=25.00, policy_term_years=20
- **Previously broken:** `policyType` was never set during highlight sequence — Vue's select v-model reset it. `preparePolicyData()` fell through to default branch, no API call was made
- **Fix:** Set `policyType` and `life_policy_type` immediately in `pendingFill` handler before starting field sequence
- **Note:** Only tested when already on /protection page. Cross-page navigation issue (Problem 1 below) still applies

### DC Pension — WORKING (fixed commits `02cb90e`, `56f73fe`, `770c9d8`, `4e8c46e`)

- **Test:** "Add my Aviva workplace pension worth £22,000 with 4% employee and 5% employer contributions"
- **Result:** Modal opens on /net-worth/retirement → pension_type: Occupational (Workplace) selected → fields fill (scheme_name, provider: Aviva, current_fund_value: 22000, employee: 4%, employer: 5%) → auto-submits → pension saved to DB → chat confirms
- **DB verification:** `dc_pensions` record: scheme_name=Aviva Workplace Pension, scheme_type=workplace, provider=Aviva, current_fund_value=22000, employee=4%, employer=5%
- **Issues found and fixed:**
  1. Route was `/net-worth/pensions` (doesn't exist) → fixed to `/net-worth/retirement`
  2. `pension_type` dropdown value `workplace` didn't match form's `occupational` → added match() mapping
  3. `pension_type` not set during highlight sequence (same as protection) → pre-set in pendingFill handler
  4. `scheme_type` not synced (validation checks `scheme_type` not `pension_type`) → call `handlePensionTypeChange()` after pre-setting
  5. `scheme_name` empty — AI puts name in `provider` not `scheme_name` → added cross-fallback
  6. `annual_salary` was required for workplace pensions → removed validation (salary is on user profile, not needed on pension form)
- **Cross-page tested:** From /protection → navigated to /net-worth/retirement, modal opened, fields filled, pension saved
- **Note:** Monte Carlo projection shows £0 — test user has no DOB set, so projection can't calculate horizon. Pre-existing limitation, not caused by form fill changes

### Liabilities — WORKING (commits `3dbbe5a`, `1fb683d`, `8161f42`, `d4e05bf`, `cbf5dc4`)
- **Test:** "I have a Barclays personal loan with £15,000 remaining at 6.5% interest, paying £350 per month"
- **Result:** Form opens on /net-worth/liabilities → fields fill (type: Personal Loan, name: Barclays Personal Loan, balance: 15000, monthly: 350, rate: 6.5) → auto-submits → card shows on page → chat confirms with payoff estimate
- **DB verification:** liability_type=personal_loan, liability_name=Barclays Personal Loan, current_balance=15000, monthly_payment=350
- **Issues found and fixed:**
  1. AI wasn't calling the tool — tool name was `create_estate_liability` → renamed to `create_liability`, same for `create_estate_asset` → `create_asset`
  2. Tool description said "estate planning" — AI didn't match credit cards to it → updated descriptions
  3. System prompt had no tool mapping — AI didn't know which tool to use for debts → added explicit mapping of user phrases to tool names
  4. Form fields not pre-set (same pattern) → added pre-set for liability_type, liability_name, current_balance
  5. Vue rendering crash after save — `addLiability` mutation pushed malformed data → removed intermediate commit, component re-fetches from API instead
- **Known minor:** Interest rate displays as 0.07% instead of 6.5% — decimal/percentage mapping issue in display

### Cross-Page Navigation + Chat Persistence — WORKING (fixed commit `38559ae`)
- **Test:** From /protection, asked "Add a Barclays easy access savings account with £5,000 at 2.8% interest"
- **Result:** Navigated to /net-worth/cash → account saved → chat panel shows user's message AND Fyn's full response AND completion confirmation
- **Previously broken:** `AiChatPanel.onOpen()` called `startNewConversation()` on every mount (every page navigation), wiping messages and creating a new conversation
- **Fix:** Check if there's already an active conversation with messages or streaming before starting a new one

---

## What Does NOT Work

**Evidence:** Protection test from dashboard — navigated to /protection, but no modal opened, no form fill, no policy saved. Chat lost all messages.

**Affected:** ANY entity type when the user is not already on the target page. Works when user IS already on the correct page.

**Root cause theory:** The AiChatPanel component remounts on SPA navigation (different ref numbers observed). The SSE stream runs in a Vuex action (survives remount), but the pendingNavigation watcher on the NEW component instance may re-trigger navigation, causing a cascade. The conversation state appears to be lost.

**Potential fixes:**
- Prevent the AI from calling `navigate_to_page` separately when it's about to call a `create_*` tool (system prompt change)
- Make the `fill_form` handler's navigation more robust — check if already on the target route before navigating
- Investigate why conversation messages disappear on navigation (may be a pre-existing bug)

### Problem 2: Protection Policy Form Submit Not Persisting — FIXED (commit `41022cc`)

**Root cause found:** `formData.policyType` was never being set during the AI fill sequence. The `policyType` field controls which conditional sections of the form render (life vs critical illness vs income protection). When set via the 250ms highlight sequence, Vue's select `v-model` reset it before the next tick. `preparePolicyData()` then saw an empty `policyType`, fell through to the default (income protection) branch, sent `benefit_amount` instead of `sum_assured`, and the parent's `switch(policyType)` had no matching case — so no API call was made.

**Fix:** Set `policyType` and `life_policy_type` immediately in the `pendingFill` handler BEFORE starting the field sequence. These fields control conditional rendering and must be set before other fields are processed.

**Browser verified:** Aviva level term life insurance, £250,000, £25/month, 20 years — saved to DB with correct `policy_type`, `sum_assured`, `premium_amount`, `policy_term_years`. Full gap analysis renders on page.

**Pattern to apply:** Any form with conditional sections (v-if on a select value) needs the controlling field set upfront, not via the highlight sequence. Check: PropertyForm (`has_mortgage` toggle), DCPensionForm (`pension_type`), AccountForm (`account_type`).

### Problem 3: Savings Institution Field Always Null

**Symptom:** The `institution` column is null in saved savings accounts even though the user said "Halifax" or "Nationwide".

**Root cause:** The AI tool definition has both `account_name` (required) and `institution` (optional). The AI puts the bank name in `account_name` ("Halifax Easy Saver") and sends `institution` as empty string `""`. The PHP handler had `$input['institution'] ?? $input['account_name']` — but `""` is not null, so `??` doesn't trigger the fallback.

**Fixed (not yet re-tested):** Changed to `!empty($input['institution']) ? $input['institution'] : (!empty($input['provider']) ? $input['provider'] : $input['account_name'])` in commit `5eca8c7`.

---

## What Has NOT Been Tested At All

The following entity types have code in place (Vue components + CoordinatingAgent handlers) but have ZERO browser testing:

| Entity Type | Page Component | Form Component | Handler Converted |
|---|---|---|---|
| DC Pension | PensionList.vue | DCPensionForm.vue | Yes |
| DB Pension | PensionList.vue | DBPensionForm.vue | Yes |
| Property | PropertyList.vue | PropertyForm.vue (multi-step) | Yes |
| Mortgage | PropertyList.vue | PropertyForm.vue | Yes |
| Estate Asset | EstateDashboard.vue → AssetsLiabilities.vue | AssetForm.vue | Yes |
| Estate Liability | LiabilitiesList.vue | LiabilityForm.vue | Yes |
| Estate Gift | EstateDashboard.vue → GiftingStrategy.vue | GiftForm.vue | Yes |
| Trust | TrustsDashboard.vue | TrustFormModal.vue | Yes |
| Business Interest | BusinessInterestsList.vue | BusinessInterestForm.vue | Yes |
| Chattel | ChattelsList.vue | ChattelFormModal.vue | Yes |
| Goal | GoalsDashboard.vue | GoalFormModal.vue | Yes |
| Life Event | GoalsDashboard.vue → EventsTab.vue | LifeEventForm.vue | Yes |
| Family Member | UserProfile.vue → FamilyMembers.vue | FamilyMemberFormModal.vue | Yes |
| Update Record | Various | Various | Yes |

Each of these could have the same field mapping issues that savings and investments had (AI field names vs form field names).

---

## Commits on aiFormFill Branch

| # | Hash | Message |
|---|---|---|
| 1 | `b1f5d1c` | Foundation: aiFormFill store, CSS highlight class, fill_form SSE event |
| 2 | `11dd286` | Savings account proof of concept |
| 3 | `70553fb` | Edit/update flow with changed-field-only animation |
| 4 | `38090f3` | Goals, life events, family members (Group D) |
| 5 | `26ce6be` | Multi-step PropertyForm support |
| 6 | `6f9dbe0` | Chat confirmation after successful save |
| 7 | `86a8d51` | All remaining entity types (Groups A/B/C/E) — 24 files |
| 8 | `d62e171` | Fix: missing `dispatch` in sendMessage action |
| 9 | `5eca8c7` | Fix: field mapping, timing (10s fallback), mounted checks |
| 10 | `41022cc` | Fix: protection policyType — set before field sequence |
| 11 | `85ae3ab` | Fix: correct routes for pension and chattel |
| 12 | `32fc87d` | Fix: pre-set pension_type and hasMortgage before field sequence |
| 13 | `02cb90e` | Fix: DC pension — call handlePensionTypeChange after pre-setting |
| 14 | `56f73fe` | Fix: DC pension — pre-set scheme_name from provider fallback |
| 15 | `770c9d8` | Fix: pension handler — fallback scheme_name to provider and vice versa |
| 16 | `4e8c46e` | Fix: remove annual salary requirement from DC pension form |
| 17 | `38559ae` | Fix: preserve chat conversation on cross-page navigation |
| 18 | `4a1b646` | Fix: savings — pre-set institution and account_type |
| 19 | `3dbbe5a` | Fix: update liability/asset tool descriptions — not estate-specific |
| 20 | `1fb683d` | Fix: rename create_estate_liability→create_liability, create_estate_asset→create_asset |
| 21 | `8161f42` | Fix: add explicit tool mapping to system prompt for all entity types |
| 22 | `d4e05bf` | Fix: liability form fill — pre-set fields, expand type validation |
| 23 | `cbf5dc4` | Fix: liabilities page Vue error after save |

**80 agent tests passing.** No Pest test failures.

---

## Recommended Fix Order (Updated)

### ~~Fix: Protection form submit~~ — DONE (commit `41022cc`)
### ~~Fix: DC Pension~~ — DONE (commits `02cb90e` through `4e8c46e`)
### ~~Fix: Conditional field pre-setting~~ — DONE for protection, pension, property
### ~~Fix: Cross-page chat context~~ — DONE (commit `38559ae`)

### Fix 1: Field mapping audit + test remaining entity types (HIGH)
Go through each remaining handler: do it yourself manually first, understand the form fields, then match the AI handler. Remaining untested: DB pension, property, mortgage, estate asset/liability/gift, trust, business interest, chattel, goal, life event, family member.

### Fix 2: Edit/update flow (LOW — untested)
The `handleUpdateRecord` handler returns `fill_form` with `mode: 'edit'`. Page watchers check for edit mode and open the edit modal. Not tested in browser.

---

## Files Changed (Full List)

### Backend (2 files)
- `app/Agents/CoordinatingAgent.php` — 16 handlers converted to fill_form
- `app/Traits/HasAiChat.php` — fill_form SSE event yield

### Frontend Store (3 files)
- `resources/js/store/modules/aiFormFill.js` — NEW: coordination store
- `resources/js/store/modules/aiChat.js` — fill_form case in SSE handler
- `resources/js/store/index.js` — register aiFormFill module

### CSS (1 file)
- `resources/css/app.css` — `.ai-fill-highlight` class

### Page Components with modal trigger watchers + mounted checks (10 files)
- `resources/js/views/NetWorth/CashOverview.vue`
- `resources/js/components/NetWorth/InvestmentList.vue`
- `resources/js/components/NetWorth/PensionList.vue`
- `resources/js/components/NetWorth/PropertyList.vue`
- `resources/js/views/Protection/ProtectionDashboard.vue`
- `resources/js/views/Estate/EstateDashboard.vue`
- `resources/js/components/NetWorth/LiabilitiesList.vue`
- `resources/js/views/Trusts/TrustsDashboard.vue`
- `resources/js/components/NetWorth/BusinessInterestsList.vue`
- `resources/js/components/NetWorth/ChattelsList.vue`

### Form Components with highlight bindings + fill watchers (15 files)
- `resources/js/components/Savings/SaveAccountModal.vue`
- `resources/js/components/Investment/AccountForm.vue`
- `resources/js/components/Investment/StandardInvestmentFields.vue`
- `resources/js/components/Retirement/DCPensionForm.vue`
- `resources/js/components/Retirement/DBPensionForm.vue`
- `resources/js/components/NetWorth/Property/PropertyForm.vue`
- `resources/js/components/Protection/PolicyFormModal.vue`
- `resources/js/components/Estate/AssetForm.vue`
- `resources/js/components/Estate/AssetsLiabilities.vue`
- `resources/js/components/Estate/GiftForm.vue`
- `resources/js/components/Estate/GiftingStrategy.vue`
- `resources/js/components/Estate/LiabilityForm.vue`
- `resources/js/components/Trusts/TrustFormModal.vue`
- `resources/js/components/NetWorth/BusinessInterestForm.vue`
- `resources/js/components/NetWorth/ChattelFormModal.vue`
- `resources/js/components/NetWorth/ChattelsList.vue`

### View components (Goals/Family — Group D, committed earlier)
- `resources/js/views/Goals/GoalsDashboard.vue`
- `resources/js/components/Goals/GoalFormModal.vue`
- `resources/js/components/Goals/LifeEventForm.vue`
- `resources/js/components/Goals/EventsTab.vue`
- `resources/js/views/UserProfile.vue`
- `resources/js/components/UserProfile/FamilyMembers.vue`
- `resources/js/components/UserProfile/FamilyMemberFormModal.vue`
