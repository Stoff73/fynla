# Assets Step — Inline Forms + Contextual Sidebar Plan

**Date:** 20 March 2026
**Goal:** Make AssetsStep render forms inline (not modals) with contextual learning sidebar content that updates based on what the user is viewing and filling in.

---

## Current State

AssetsStep.vue renders 6 form components (PropertyForm, AccountForm, SaveAccountModal, DCPensionForm, DBPensionForm, StatePensionForm) without `context="onboarding"`, so they render as full-screen modals with backdrop overlays. This covers the learning sidebar.

All 6 form components already support `context="onboarding"` — they strip the modal wrapper and render inline. This is already built and tested.

## What Needs to Change

### 1. Add `context="onboarding"` to all form renders in AssetsStep.vue

**File:** `resources/js/components/Onboarding/steps/AssetsStep.vue`
**Lines:** ~404-450 (the form rendering section)

Add `context="onboarding"` to:
- `<PropertyForm>` (line 404)
- `<AccountForm>` (line 413)
- `<SaveAccountModal>` (line 423)
- `<DCPensionForm>` (line 431)
- `<DBPensionForm>` (line 439)
- `<StatePensionForm>` (line 446)

This makes them render inline instead of as modals. No other changes to the form components.

### 2. Move form rendering into the tab content area (not after it)

Currently the forms render AFTER the tab content `</div>`. They need to render INSIDE each tab's `v-show` block, replacing the card list when a form is open. Pattern:

```html
<!-- Cash Tab -->
<div v-show="activeTab === 'cash'">
  <!-- Cards (when no form open) -->
  <div v-if="!showSavingsForm">
    <!-- existing card list + add button -->
  </div>
  <!-- Inline form (when adding/editing) -->
  <SaveAccountModal
    v-if="showSavingsForm"
    context="onboarding"
    :account="editingSavings"
    @close="closeSavingsForm"
    @save="handleSavingsSaved"
  />
</div>
```

Same pattern for each tab. The form replaces the cards, sidebar stays visible.

### 3. Add `sidebarContext` tracking

**New ref:** `sidebarContext` — tracks what the user is currently viewing.

Values:
- `'cash-list'` — viewing cash tab cards
- `'cash-form'` — filling in savings account form
- `'retirement-list'` — viewing pension tab cards
- `'retirement-form-dc'` — filling in DC pension form
- `'retirement-form-db'` — filling in DB pension form
- `'retirement-form-state'` — filling in state pension form
- `'investments-list'` — viewing investments tab cards
- `'investments-form'` — filling in investment account form
- `'properties-list'` — viewing properties tab cards
- `'properties-form'` — filling in property form

Updated when:
- Tab changes → `{tab}-list`
- Form opens → `{tab}-form`
- Form closes → `{tab}-list`

### 4. Add sidebar content definitions

**New computed:** `sidebarContent` — returns `{ didYouKnow, whyWeAsk, howItFits, quickStat }` based on `sidebarContext`.

```javascript
const SIDEBAR_CONTENT = {
  'cash-list': {
    didYouKnow: 'A six-month emergency fund is the single most important financial protection you can have...',
    whyWeAsk: 'Knowing your savings lets us calculate how many months of expenses you have covered...',
    quickStat: { value: '£20,000', label: 'Your annual ISA allowance (2025/26)' },
  },
  'cash-form': {
    didYouKnow: 'The best easy access savings accounts currently pay over 4.5% AER. If your money is sitting in a current account earning 0%, moving it could earn you hundreds per year.',
    whyWeAsk: 'The institution, account type, and balance let us track your emergency fund progress, flag if you are missing ISA tax benefits, and monitor whether your rate is competitive.',
    quickStat: { value: '4.5%+', label: 'Best easy access rates available right now' },
  },
  'retirement-list': {
    didYouKnow: 'Auto-enrolment means your employer must contribute to your pension if you earn above £10,000...',
    whyWeAsk: 'Your pension details let us project your retirement income and assess whether you are on track...',
    quickStat: { value: '£60,000', label: 'Annual pension allowance (2025/26)' },
  },
  'retirement-form-dc': {
    didYouKnow: 'Every £1 of salary sacrifice into your pension saves income tax AND National Insurance. At the basic rate, that is 32p saved per £1 contributed.',
    whyWeAsk: 'Your scheme name, provider, fund value, and contribution percentages let us calculate your projected pension pot at retirement and identify if you should increase contributions.',
    quickStat: { value: '32p', label: 'Saved per £1 via salary sacrifice at basic rate' },
  },
  'retirement-form-db': {
    didYouKnow: 'Defined Benefit pensions guarantee an income for life, linked to your salary and years of service. They are increasingly rare and extremely valuable.',
    whyWeAsk: 'Your guaranteed annual pension amount is a cornerstone of retirement income planning. Combined with State Pension, it may cover your essential spending entirely.',
    quickStat: { value: 'Guaranteed', label: 'DB pensions provide income for life regardless of markets' },
  },
  'retirement-form-state': {
    didYouKnow: 'The full new State Pension is £11,502 per year (2025/26). It is uprated annually by the triple lock — the highest of earnings growth, CPI inflation, or 2.5%.',
    whyWeAsk: 'Your State Pension forecast and qualifying years let us project your guaranteed baseline income in retirement.',
    quickStat: { value: '£11,502', label: 'Full new State Pension annual amount (2025/26)' },
  },
  'investments-list': {
    didYouKnow: 'A Stocks and Shares ISA lets you invest up to £20,000 per year with all growth and income completely free of tax — forever.',
    whyWeAsk: 'Knowing your existing investments lets us assess diversification, flag tax inefficiency, and incorporate your portfolio into net worth and retirement projections.',
    quickStat: { value: '£20,000', label: 'Annual ISA allowance — all growth tax-free (2025/26)' },
  },
  'investments-form': {
    didYouKnow: 'Platform fees compound over time just like returns. A 0.5% difference in annual fees on a £100,000 portfolio costs over £50,000 over 30 years.',
    whyWeAsk: 'Your provider, account type, value, and fees let us calculate the true cost of your investments and identify where fee reductions could significantly improve long-term returns.',
    quickStat: { value: '£50,000', label: 'Cost of 0.5% extra fees on £100k over 30 years' },
  },
  'properties-list': {
    didYouKnow: 'Most homeowners overpay their mortgage by not reviewing their rate every two years. Even a 0.5% rate reduction on a £250,000 mortgage saves over £1,200 per year.',
    whyWeAsk: 'Your property and mortgage details let us calculate your equity, net worth, potential remortgage savings, and whether a decreasing term life policy should be tied to your outstanding balance.',
    quickStat: { value: '2 years', label: 'How often you should review your mortgage rate' },
  },
  'properties-form': {
    didYouKnow: 'Your main residence is exempt from Capital Gains Tax when sold. Buy-to-let properties are not — and the rate is 24% for higher-rate taxpayers. Ownership structure matters.',
    whyWeAsk: 'The address, value, ownership type, and mortgage details feed into your net worth, estate planning, protection needs calculation, and rental yield analysis.',
    quickStat: { value: '24%', label: 'Capital Gains Tax on residential property for higher-rate taxpayers' },
  },
};
```

### 5. Emit sidebar content to OnboardingWizard

AssetsStep emits the sidebar content so the OnboardingWizard's complementary column (right side) can display it. Two options:

**Option A:** AssetsStep manages its own sidebar content via a slot or internal section.

**Option B:** AssetsStep emits a `sidebar-update` event with the content object, and OnboardingWizard passes it to the learning sidebar.

**Recommended: Option A** — AssetsStep already wraps in `<OnboardingStep>` which has a complementary slot. Add the sidebar content inside AssetsStep's template directly, so it's self-contained. No changes to OnboardingWizard needed.

The `<OnboardingStep>` component likely has a slot or section for the learning sidebar. If not, AssetsStep can render its own sidebar content in the complementary column.

### 6. Update OnboardingStep.vue to accept sidebar content as a slot

Check if OnboardingStep already has a sidebar slot. If yes, AssetsStep passes content into it. If not, add one.

---

## Files to Change

| File | Change | Effort |
|------|--------|--------|
| `AssetsStep.vue` | Add `context="onboarding"` to all 6 forms, move forms into tab content blocks, add `sidebarContext` tracking, add `SIDEBAR_CONTENT`, render sidebar content | Medium |
| `OnboardingStep.vue` | Add sidebar slot if not already present | Small |

**Zero new files. Zero new components.**

---

## What Does NOT Change

- Form components (PropertyForm, AccountForm, SaveAccountModal, DCPensionForm, DBPensionForm, StatePensionForm) — no changes, they already support `context="onboarding"`
- Tab filtering by life stage config — already working
- Card list pattern — already working
- Save to DB flow — already working
- OnboardingWizard — no changes needed
- lifeStageConfig — no changes needed

---

## User Experience Flow

1. User reaches Assets step → sees tab bar (filtered by journey) + card list for first tab
2. Sidebar shows tab-level guidance ("Why emergency funds matter")
3. User clicks "+ Add Account" → card list hides, inline form appears in same column
4. Sidebar updates to form-level guidance ("What interest rate to look for")
5. User fills form, clicks Save → form hides, card list returns with new card
6. Sidebar reverts to tab-level guidance
7. User clicks different tab → sidebar updates to that tab's guidance
8. User adds items across tabs, clicks Continue when done
