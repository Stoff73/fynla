# Life Stage Journey System — Design Specification

**Date:** 2026-03-17
**Status:** Approved
**Version:** 1.0

---

## 1. Overview

Fynla's entire user experience will be organised around the **5 UK financial planning life stages**. Life stages become the primary journeys, replacing the existing journey system. Every touchpoint — onboarding, sidebar, dashboard, learning content, and demo experience — adapts based on the user's selected stage.

### Design Principles

- **Progressive reveal** — show what's relevant now, make everything else accessible but not cluttering
- **Education alongside action** — every data input has contextual learning explaining why it matters
- **Dynamic growth** — the app adapts as users add data or experience life events, without requiring manual stage changes
- **Never locked** — no module is ever hidden or inaccessible; secondary modules live in "Explore more"

---

## 2. Life Stages & Personas

### 2.1 Stage Definitions

| # | Stage ID | Label | Tagline | Age Range | Stage Colour |
|---|----------|-------|---------|-----------|-------------|
| 1 | `university` | Starting Out | Build smart money habits from day one | 18–25 | Violet (`violet-500`) |
| 2 | `early_career` | Building Foundations | Save for your first home and grow your career | 23–35 | Spring (`spring-500`) |
| 3 | `mid_career` | Protecting What Matters | Secure your family and grow your wealth | 31–50 | Raspberry (`raspberry-500`) |
| 4 | `peak` | Planning Your Future | Maximise your wealth and prepare for retirement | 46–65 | Light Blue (`light-blue-500`) |
| 5 | `retirement` | Enjoying Your Wealth | Make your money last and leave a legacy | 65+ | Horizon (`horizon-500`) |

### 2.2 Persona Mapping (6 personas, 5 stages)

| Stage | Persona(s) | Preview ID |
|-------|-----------|------------|
| Starting Out | Janice Taylor, 21, student | `student` |
| Building Foundations | John Morgan, ~28, early career (amended from 24) | `young_saver` |
| Protecting What Matters | James & Emily Carter, 32-34 | `young_family` |
| Protecting What Matters | Alex Chen, 38, entrepreneur | `entrepreneur` |
| Planning Your Future | David & Sarah Mitchell, late 40s | `peak_earners` |
| Enjoying Your Wealth | Patricia & Harold Bennett, 70-72 | `retired_couple` |

### 2.3 Changes to Existing Personas

- **REMOVE**: Margaret Thompson (`widow`) persona — deleted from PreviewUserSeeder, persona JSON, PreviewController, and ResetPreviewData command
- **AMEND**: John Morgan (`young_saver`) — age up to ~28, give him early career data (workplace pension with meaningful balance, LISA for first home, career income ~£35-40k, emergency fund goal, house deposit goal)
- **ADD** `life_stage` field to ALL six persona JSON files: `student.json`, `young_saver.json`, `young_family.json`, `entrepreneur.json`, `peak_earners.json`, `retired_couple.json`

---

## 3. Architecture: Life Stage Configuration

### 3.1 Central Config Object

A single configuration source drives the entire stage-adaptive experience. Every component reads from this config rather than having scattered conditional logic.

**Frontend:** `resources/js/constants/lifeStageConfig.js`
**Backend:** `app/Services/LifeStage/LifeStageService.php`

```javascript
// resources/js/constants/lifeStageConfig.js
export const LIFE_STAGES = {
  university: {
    id: 'university',
    label: 'Starting Out',
    tagline: 'Build smart money habits from day one',
    ageRange: '18–25',
    persona: 'student',
    icon: 'graduation-cap',
    colour: 'violet',

    sidebar: {
      primary: ['dashboard', 'bank-accounts', 'income', 'expenditure', 'savings', 'goals', 'risk-profile'],
      promoted: [],  // dynamically added based on user data
    },

    dashboard: {
      hero: 'journey-progress',
      cards: ['budget-tracker', 'student-loan', 'savings', 'goals', 'life-timeline'],
    },

    onboarding: {
      steps: ['personal-info', 'student-loan', 'income', 'expenditure', 'savings', 'goals'],
      learningMilestones: { /* per-step content — see Section 7 */ },
    },

    suggestedGoals: ['emergency-fund', 'travel-fund', 'graduate-debt-free', 'save-for-car'],

    learning: {
      pensionContext: 'auto-enrolment-from-22',
      savingsContext: 'emergency-fund-basics',
      debtContext: 'student-loan-not-like-debt',
    },
  },

  early_career: { /* ... */ },
  mid_career: { /* ... */ },
  peak: { /* ... */ },
  retirement: { /* ... */ },
};
```

### 3.2 Stage Detection

The user's life stage is set in three ways:

1. **Onboarding selection** — user picks their stage on the welcome screen (primary method)
2. **Preview mode** — auto-set from persona's `preview_persona_id` → stage mapping
3. **Profile update** — when DOB, marital status, or family situation changes significantly, the system may suggest a stage transition (always optional, never forced)

### 3.3 Database Changes

Add to `users` table:
```sql
ALTER TABLE users ADD COLUMN life_stage VARCHAR(20) NULL DEFAULT NULL;
-- Values: 'university', 'early_career', 'mid_career', 'peak', 'retirement'
```

Add to preview persona JSON files:
```json
{
  "life_stage": "university"
}
```

---

## 4. Welcome Screen & "Find Your Stage"

### 4.1 Dual-Purpose Screen

This screen serves both contexts:
- **Landing page** — potential users browse stages, click to enter preview demo
- **Onboarding** — new sign-ups select their stage to begin their journey

### 4.2 Layout

**Top section:** "Where are you in your financial journey?" heading with 5 life stage cards in a horizontal row (single column on mobile).

Each card contains:
- Stage-coloured gradient icon circle
- Stage label (e.g. "Starting Out")
- Age range in stage colour (e.g. "Ages 18–25")
- One-line tagline

**Bottom section:** "or focus on a specific area" divider with 4 focus area shortcut pills:
- Cash & Budget
- Protection
- Investment
- Retirement

### 4.3 Behaviour

- **Hover** — card lifts with shadow, border becomes stage colour
- **Click (landing page)** — opens Journey Map modal, then "See It in Action" enters demo
- **Click (onboarding)** — opens Journey Map modal, then "Start My Journey" begins onboarding
- **Focus area click** — goes directly to mini-onboarding for that module (existing flow)
- **Mobile** — cards stack vertically, focus areas become 2×2 grid

---

## 5. Journey Map (Preview)

### 5.1 Design

When a user clicks a life stage card, a Journey Map modal appears showing the full path for that stage.

**Visual:** An SVG meandering path that winds horizontally across the screen. Nodes are positioned along the curved dashed trail line, alternating between upper and lower positions. The path then drops vertically before curving back to a green destination node.

**Path characteristics:**
- Dashed line (`stroke-dasharray: 8,6`) using a gradient from stage colour to spring-500
- Shadow path for depth (`stroke-width: 8, opacity: 0.08`)
- Nodes are numbered circles in stage colour with decreasing opacity (1.0 → 0.5)
- First node has a glow filter
- Destination node is spring-500 with flag icon

**Labels:**
- Consistent 28px gap between node edge and nearest label text
- Labels positioned opposite to the path direction (below for top-row nodes, above for bottom-row nodes, to the side for vertical sections)
- Each label has a title (font-weight 700, horizon-500) and 1-2 lines of description (neutral-500)

**Interaction:**
- Tapping/clicking a node reveals an expanded detail card below the map with the full educational description for that step
- "Tap any step to learn more" hint text

**CTA area:**
- "Start My Journey" button (raspberry-500)
- "See It in Action" button (white, border)
- "You can skip steps and come back to them later" reassurance text

### 5.2 Steps Per Stage

**Starting Out (6 steps):** About You → Student Loan → Income → Spending → Savings → Goals

**Building Foundations (7 steps):** About You → Income & Career → Savings & Emergency Fund → First Home & LISA → Pension & Auto-enrolment → Investments → Goals

**Protecting What Matters (8 steps):** About You → Family → Income → Property & Mortgage → Protection & Insurance → Pensions → Will & Estate → Goals

**Planning Your Future (7 steps):** About You → Income & Tax → Pension Review → Investments & ISA → Property Portfolio → Estate & IHT → Goals

**Enjoying Your Wealth (6 steps):** About You → Pension & Drawdown → State Pension → Income & Tax → Estate & Legacy → Goals

---

## 6. Onboarding Flow

### 6.1 Layout

Two-column layout on desktop:

**Left column (60%):** Clean form inputs for the current step. Navigation at bottom: Back / Skip this step / Continue.

**Right column (360px):** Learning sidebar with three sections:
1. **"Did you know?"** — the learning milestone. Educational, engaging, stage-contextualised content
2. **"Why we ask this"** — explains what this specific data point does in their plan
3. **"How this fits your journey"** — connects the input to their life stage goals

Plus an optional **Quick stat** — a concrete, memorable number (e.g. "£27,295 — Plan 5 repayment threshold")

### 6.2 Progress Bar

Compact journey progress indicator at the top of the onboarding screen:
- Completed steps: spring-500 circle with tick
- Current step: stage-colour circle with glow ring, label in stage colour
- Upcoming steps: light-gray circles with neutral-500 numbers
- Steps connected by lines (spring-500 for completed, light-gray for upcoming)

### 6.3 Mobile Behaviour

- Learning sidebar collapses below the form as a "Learn more" accordion
- Progress bar scrolls horizontally if needed
- Single-column form

---

## 7. Adaptive Sidebar

### 7.1 Expanded State (w-56)

**Stage badge:** Below the logo, shows current journey name and age range in the stage colour (e.g. "Starting Out · Ages 18–25" in violet-500).

**Journey progress bar:** Compact horizontal bar below the stage badge. Shows completion percentage in stage colour with gradient to spring-500.

**Primary items:** Defined per stage in `lifeStageConfig.sidebar.primary`. Displayed as standard menu items using existing `SideMenuItem.vue` and `SideMenuIcon.vue` SVG icons.

**Divider**

**"Explore more" section:** Collapsible section at the bottom containing every module NOT in the primary list. Items are visually muted (neutral-500 text, reduced icon opacity) but fully functional clickable links. Expands/collapses with chevron indicator.

**Dynamic promotion:** If a user adds data for a module currently in "Explore more" (e.g. a student adds a property), that module automatically moves to the primary section. Managed by the `lifeStageConfig` + user data flags.

**Active state:** Uses stage colour (violet for student, raspberry for mid-career) instead of a fixed colour.

### 7.2 Collapsed State (w-16, default)

**Progress ring:** Circular SVG ring wraps around the favicon/logo, showing journey completion percentage in the stage colour. Tiny percentage text below. Tooltip on hover: "Journey: 67% complete".

**Icon-only primary items:** Existing `SideMenuIcon.vue` SVGs with active state using stage colour background + left border indicator. Tooltips show labels on hover.

**"Explore more" becomes ⋮:** Three vertical dots icon. On hover, a flyout panel slides out to the right with all additional modules listed with their labels and icons.

### 7.3 Primary Items Per Stage

| Stage | Primary Sidebar Items |
|-------|----------------------|
| Starting Out | Dashboard, Bank Accounts, Income, Expenditure, Savings, Goals, Risk Profile |
| Building Foundations | Dashboard, Bank Accounts, Income, Expenditure, Savings, Investments, Retirement, Goals |
| Protecting What Matters | Dashboard, Property & Mortgage, Protection, Investments, Retirement, Will, Bank Accounts, Goals |
| Planning Your Future | Dashboard, Investments, Retirement, Property, Estate Planning, Protection, Plans, Goals |
| Enjoying Your Wealth | Dashboard, Retirement, Estate Planning, Investments, Property, Trusts, Plans, Goals |

### 7.4 Mobile Tab Bar

Maps the top 4-5 primary items from the stage config to the bottom tab bar. "More" tab shows everything else. The Learn tab becomes the primary place for stage-contextualised education.

---

## 8. Stage-Curated Dashboard

### 8.1 Structure

Built on existing codebase patterns:

**Journey progress hero (NEW):** Always at the top. Shows greeting, stage name + completion %, progress bar with stage-colour gradient, and a "Next step" CTA with educational context. Compact horizontal layout.

**Module cards (EXISTING pattern, stage-curated):** Uses existing `.card` CSS class, 3-column grid (`lg:grid-cols-3 gap-3`). Cards shown and their order defined by `lifeStageConfig.dashboard.cards`. Each card follows the existing pattern:
- Title (font-weight 700, horizon-500)
- Primary metric (font-weight 900, large)
- Secondary metrics (text-sm)
- Plan-driven actions (max 2 per card, decision-engine plain English titles, no icons — just the text)

**Net Worth card (EXISTING):** ApexCharts donut with `ASSET_COLORS` from `designSystem.js`. Centre label shows formatted net worth. Assets (violet-600) vs Liabilities (raspberry-600) below.

**Goals card (NEW):** Shows active goals with progress bars (amounts, percentages, timelines). Stage-suggested goals appear as dashed border cards at the bottom. "+ Add goal" CTA in header.

**Life Timeline card (NEW):** Vertical timeline in the card showing past events (spring-500 dots), imminent events (stage-colour with glow), and planned future events (light-gray dots). "What if →" and "+ Add event" links in header. Imminent events link to "See how this affects your plan".

### 8.2 Cards Per Stage

| Stage | Dashboard Cards |
|-------|----------------|
| Starting Out | Budget Tracker, Student Loan, Savings, Goals, Life Timeline |
| Building Foundations | Net Worth, Savings, Investments, Retirement, Goals, Life Timeline |
| Protecting What Matters | Net Worth, Protection, Cash & Savings, Investments, Retirement, Estate, Goals, Life Timeline |
| Planning Your Future | Net Worth, Retirement, Investments, Estate, Protection, Tax Allowances, Goals, Life Timeline |
| Enjoying Your Wealth | Net Worth, Retirement (income view), Estate, Investments, Tax Allowances, Goals, Life Timeline |

### 8.3 Dynamic Card Appearance

Cards only appear when the user has relevant data. If a student adds a property, a Property card appears automatically. The `lifeStageConfig` defines the potential card set; user data determines which actually render.

### 8.4 Mobile Dashboard

Same stage-adaptive approach. Single column, journey hero stays at top, cards stack vertically. Same content, adapted for smaller screen.

---

## 9. Goals & Life Events

### 9.1 Stage-Suggested Goals

Each life stage surfaces relevant goals the user might not think of. Defined in `lifeStageConfig.suggestedGoals`. Shown as dashed-border suggestion cards in the Goals dashboard card and in the full Goals view.

| Stage | Suggested Goals |
|-------|----------------|
| Starting Out | Emergency fund (3 months), Save for a car, Graduate debt-free, Travel fund |
| Building Foundations | House deposit (LISA), 6-month emergency fund, Start investing (Stocks & Shares ISA), Wedding fund |
| Protecting What Matters | Pay off mortgage early, Children's education fund, Retire at 60, Close protection gaps |
| Planning Your Future | Maximise pension contributions, Downsize property, Fund care costs, Leave inheritance |
| Enjoying Your Wealth | Sustainable income target, Gift to family, Fund care needs, Legacy plan |

### 9.2 Life Events as Triggers

When a user adds a material life event, the `lifeStageConfig` re-evaluates:

1. **Module promotion** — relevant modules move from "Explore more" to primary sidebar
2. **Dashboard cards** — new cards appear for relevant modules
3. **Action re-prioritisation** — the decision engine recalculates with new context
4. **Stage transition suggestion** — if the event implies a stage change (see 9.3)

**Example life event responses:**

| Event | Module Promotions | Dashboard Changes | May Trigger Stage Transition? |
|-------|-------------------|-------------------|-------------------------------|
| Having a baby | Protection, Will | Protection card appears with dependant context | Yes → Protecting What Matters |
| Buying a house | Property | Property/Mortgage card appears | Yes → Building Foundations or Protecting What Matters |
| Redundancy | (none — emergency priority) | Emergency fund becomes prominent, budget recalculated | No |
| Receiving inheritance | Investments, Estate | Investment + Estate cards appear | No (module promotion only) |
| Marriage | (none — family update) | Joint assets recalculated | Possible → Protecting What Matters |
| Retirement | Retirement (income view) | Retirement card switches to decumulation view | Yes → Enjoying Your Wealth |

### 9.3 Stage Transition Prompt

When life events or profile changes suggest the user has outgrown their current stage, a friendly modal appears:

- Shows current stage → suggested stage with icons and labels
- Explains what changes (sidebar, dashboard, guidance)
- Two CTAs: "Update My Journey" (primary) / "Stay Where I Am" (secondary)
- "You can change this any time in Settings" reassurance
- **Always optional, never forced**

---

## 10. Learning System

### 10.1 Stage-Contextualised Content (Existing Touchpoints)

All existing learning features adapt their language and examples to the user's stage:

- **Info Guide panel** — requirements and completion context adjusted per stage
- **Fyn AI chat** — system prompt includes stage context, suggested prompts adjusted
- **Tooltips** — "Why we ask" and "How it's used" content varies by stage

**Example:** A student seeing the pension page gets *"You'll be auto-enrolled from age 22 — here's why starting now matters"* while a pre-retiree gets *"You can carry forward 3 years of unused allowance — this is your window to maximise tax relief."*

### 10.2 Learning Milestones (New — Onboarding)

Embedded alongside inputs during the onboarding journey. Not separate screens — they appear in the right-hand learning sidebar as the user enters data.

Each milestone has:
- **"Did you know?"** header with educational content
- **"Why we ask this"** — what this data point does
- **"How this fits your journey"** — connects to their stage
- **Quick stat** (optional) — a concrete, memorable number

Content defined per stage per step in `lifeStageConfig.onboarding.learningMilestones`.

---

## 11. Unified Forms — One Form Per Data Type

### 11.1 Core Principle

**Every data type has exactly ONE form component.** The same form is used in onboarding, on module pages, and in any modal — there is no "onboarding version" vs "edit version". This eliminates confusion and maintenance burden.

Forms adapt their visible fields based on the user's **life stage** and **context** (onboarding step vs standalone edit), but the underlying component is always the same.

### 11.2 Current State & Required Changes

Several forms already follow this pattern (shared between onboarding and module pages):

| Data Type | Shared Form (keep) | Onboarding-Only Form (retire) |
|-----------|-------------------|-------------------------------|
| Savings Account | `SaveAccountModal.vue` | `SimpleSavingsAccountStep.vue` (retire — use SaveAccountModal) |
| Investment Account | `AccountForm.vue` | (none — already shared) |
| Insurance Policy | `PolicyFormModal.vue` | (none — already shared) |
| Goals | `GoalFormModal.vue` | `GoalSetupStep.vue` (retire — use GoalFormModal) |
| Personal Info | `PersonalInformation.vue` | `PersonalInfoStep.vue` + `SimplePersonalInfoStep.vue` (retire both — unify into PersonalInformation) |
| Income | (create unified) | `IncomeStep.vue` (retire — merge into unified income form) |
| Expenditure | `ExpenditureForm.vue` | `ExpenditureStep.vue` + `SimpleExpenditureStep.vue` (retire both — use ExpenditureForm) |
| Property | `PropertyForm.vue` | `SimplePropertyMortgageStep.vue` (retire — use PropertyForm) |
| DC Pension | `DCPensionForm.vue` | (none — already module-only) |
| DB Pension | `DBPensionForm.vue` | (none — already module-only) |
| Liabilities | `LiabilityForm.vue` | `LiabilitiesStep.vue` (retire — use LiabilityForm) |
| Family Members | `FamilyMemberFormModal.vue` | `FamilyInfoStep.vue` (retire — use FamilyMemberFormModal) |

### 11.3 Stage-Adaptive Field Visibility

Each unified form accepts a `lifeStage` prop (or reads it from the Vuex store) and a `context` prop (`'onboarding'` | `'standalone'`). Fields are shown/hidden based on a field visibility map in the `lifeStageConfig`:

```javascript
// In lifeStageConfig.js, per stage:
formFields: {
  personalInfo: {
    always: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'],
    university: ['education_level', 'university', 'student_number'],
    early_career: ['marital_status', 'occupation', 'employer', 'employment_status'],
    mid_career: ['marital_status', 'occupation', 'employer', 'employment_status', 'health_status', 'smoking_status'],
    peak: ['marital_status', 'occupation', 'employer', 'employment_status', 'health_status', 'smoking_status', 'target_retirement_age'],
    retirement: ['marital_status', 'health_status', 'smoking_status'],
    onboardingHide: ['address_line_1', 'address_line_2', 'city', 'county', 'postcode'],
    // Address fields hidden during onboarding for ALL stages (collected later in profile)
    // All fields always available in standalone context
  },
  income: {
    always: ['employment_status'],
    university: ['part_time_income', 'maintenance_loan', 'parental_support', 'bursary_grant'],
    early_career: ['annual_employment_income', 'occupation', 'employer'],
    mid_career: ['annual_employment_income', 'occupation', 'employer', 'annual_rental_income', 'annual_self_employment_income'],
    peak: ['annual_employment_income', 'occupation', 'employer', 'annual_rental_income', 'annual_self_employment_income', 'dividend_income'],
    retirement: ['pension_income', 'state_pension', 'annual_rental_income', 'dividend_income'],
  },
  // ... similar for each form type
}
```

### 11.4 Stage-Specific Field Adaptations

#### Personal Info Form — Per Stage

| Field | Starting Out | Building Foundations | Protecting What Matters | Planning Your Future | Enjoying Your Wealth |
|-------|-------------|--------------------|-----------------------|---------------------|---------------------|
| Name, DOB, Gender | Yes | Yes | Yes | Yes | Yes |
| Phone | Yes | Yes | Yes | Yes | Yes |
| Student Number | **Yes** | No | No | No | No |
| University | **Yes** | No | No | No | No |
| Education Level | **Yes** | No | No | No | No |
| Marital Status | No (onboarding) | Yes | Yes | Yes | Yes |
| Address | No (onboarding) | No (onboarding) | No (onboarding) | No (onboarding) | No (onboarding) |
| Occupation/Employer | No | Yes | Yes | Yes | No |
| Health/Smoking | No | No | Yes | Yes | Yes |
| Retirement Age | No | No | Yes | Yes | No |
| Domicile | No (onboarding) | No (onboarding) | No (onboarding) | No (onboarding) | No (onboarding) |

**Note:** "No (onboarding)" means hidden during onboarding but visible when editing in the profile page. The form component shows all fields in standalone context — the stage filter only applies during onboarding.

#### Income Form — Per Stage

| Field | Starting Out | Building Foundations | Protecting What Matters | Planning Your Future | Enjoying Your Wealth |
|-------|-------------|--------------------|-----------------------|---------------------|---------------------|
| Employment Status | Yes | Yes | Yes | Yes | No |
| Part-time Income | **Yes** | No | No | No | No |
| Maintenance Loan | **Yes** | No | No | No | No |
| Parental Support | **Yes** | No | No | No | No |
| Annual Employment Income | No | Yes | Yes | Yes | No |
| Self-Employment Income | No | No | Yes | Yes | No |
| Rental Income | No | No | Yes | Yes | Yes |
| Pension Income | No | No | No | No | **Yes** |
| State Pension | No | No | No | No | **Yes** |
| Dividend Income | No | No | No | Yes | Yes |

#### Savings Account Form — Per Stage

| Adaptation | Starting Out | Others |
|-----------|-------------|--------|
| Default account types shown first | Cash ISA, Instant Access, Current Account | All types |
| ISA guidance text | Explains LISA + first home bonus | Standard ISA explanation |
| Emergency fund prompt | Prominent — "Is this your emergency fund?" | Standard checkbox |
| Joint ownership section | Hidden (unlikely for student) | Shown if married |

#### Protection Policy Form — Per Stage

| Adaptation | Starting Out | Building Foundations | Protecting What Matters | Peak & Retirement |
|-----------|-------------|--------------------|-----------------------|-------------------|
| Shown in onboarding | No | Optional | Yes (critical step) | Yes |
| Default policy types | (n/a) | Life | Life, Critical Illness, Income Protection | All types |
| Beneficiary section | (n/a) | Simplified | Full with dependant context | Full |
| Mortgage protection | (n/a) | If has mortgage | Prominent if has mortgage | If has mortgage |

### 11.5 Implementation Pattern

Each unified form component follows this pattern:

```vue
<script setup>
import { computed } from 'vue';
import { useStore } from 'vuex';
import { LIFE_STAGES } from '@/constants/lifeStageConfig';

const props = defineProps({
  context: { type: String, default: 'standalone' }, // 'onboarding' | 'standalone'
});

const store = useStore();
const lifeStage = computed(() => store.getters['lifeStage/currentStage']);
const fieldConfig = computed(() => LIFE_STAGES[lifeStage.value]?.formFields?.personalInfo || {});

const isFieldVisible = (fieldName) => {
  // In standalone context, always show all fields
  if (props.context === 'standalone') return true;

  // In onboarding context, check stage config
  const stage = lifeStage.value;
  const alwaysFields = fieldConfig.value.always || [];
  const stageFields = fieldConfig.value[stage] || [];
  const onboardingHide = fieldConfig.value.onboardingHide || [];

  if (onboardingHide.includes(fieldName)) return false;
  return alwaysFields.includes(fieldName) || stageFields.includes(fieldName);
};
</script>
```

### 11.6 Onboarding Step Wrapper

The onboarding wizard wraps each unified form in a step container that provides:
- The progress bar header
- The learning sidebar (right column)
- The `context="onboarding"` prop
- Back / Skip / Continue navigation

```vue
<!-- OnboardingStep.vue wraps any form -->
<template>
  <div class="grid grid-cols-[1fr_360px]">
    <div class="p-8">
      <!-- The actual form component — same one used everywhere -->
      <component :is="stepComponent" :context="'onboarding'" @save="handleStepSave" />
      <StepNavigation @back="back" @skip="skip" @continue="next" />
    </div>
    <LearningMilestoneSidebar :step="currentStep" :stage="lifeStage" />
  </div>
</template>
```

### 11.7 Forms to Retire (Onboarding-Only Components)

These components will be retired once their unified equivalents support stage-adaptive fields:

| Retire | Replace With |
|--------|-------------|
| `SimplePersonalInfoStep.vue` | `PersonalInformation.vue` with `context="onboarding"` |
| `PersonalInfoStep.vue` | `PersonalInformation.vue` with `context="onboarding"` |
| `SimpleExpenditureStep.vue` | `ExpenditureForm.vue` with `context="onboarding"` |
| `ExpenditureStep.vue` | `ExpenditureForm.vue` with `context="onboarding"` |
| `SimplePropertyMortgageStep.vue` | `PropertyForm.vue` with `context="onboarding"` |
| `SimpleSavingsAccountStep.vue` | `SaveAccountModal.vue` with `context="onboarding"` |
| `LiabilitiesStep.vue` | `LiabilityForm.vue` with `context="onboarding"` |
| `FamilyInfoStep.vue` | `FamilyMemberFormModal.vue` with `context="onboarding"` |
| `GoalSetupStep.vue` | `GoalFormModal.vue` with `context="onboarding"` |
| `IncomeStep.vue` | New unified income form with `context="onboarding"` |
| `QuickAssetsStep.vue` | Remove — assets entered via individual forms per step |
| `AssetsStep.vue` | Remove — assets entered via individual forms per step |

---

## 12. Landing Page Demo Experience

### 11.1 "Find Your Stage" Interactive

The landing page shows the 5 life stage cards with the "Where are you in your financial journey?" heading. Each card shows:
- Stage icon in gradient circle
- Stage label
- Age range
- One-line tagline

Clicking a stage opens the Journey Map modal. "See It in Action" enters the preview demo for that stage's persona.

### 11.2 Preview Mode Integration

- Stage is auto-set from persona's `preview_persona_id`
- Sidebar, dashboard, and all content adapt to the persona's stage
- Preview banner shows stage context
- Persona selector grouped by stage (in the preview banner dropdown)

---

## 13. Technical Implementation Notes

### 13.1 Files to Create

| File | Purpose |
|------|---------|
| `resources/js/constants/lifeStageConfig.js` | Central stage configuration (sidebar, dashboard, onboarding, learning, goals, formFields) |
| `app/Services/LifeStage/LifeStageService.php` | Backend mirror of stage config, stage detection logic |
| `resources/js/store/modules/lifeStage.js` | Vuex module for stage state, userDataFlags, dynamic promotion |
| `resources/js/components/Journey/JourneyMap.vue` | SVG meandering path component for journey preview |
| `resources/js/components/Journey/JourneyProgressHero.vue` | Dashboard hero with greeting, progress bar, next step CTA |
| `resources/js/components/Dashboard/GoalsCard.vue` | Goals with progress bars and stage-suggested goals |
| `resources/js/components/Dashboard/LifeTimelineCard.vue` | Vertical/horizontal life event timeline |
| `resources/js/components/Shared/StageTransitionModal.vue` | Optional stage transition prompt |
| `resources/js/components/Onboarding/LearningMilestoneSidebar.vue` | Right-column learning content during onboarding |
| `database/migrations/xxxx_add_life_stage_to_users.php` | Add `life_stage` column |

### 13.2 Files to Modify

| File | Change |
|------|--------|
| `resources/js/components/SideMenu.vue` | Stage badge, progress bar/ring, primary/explore-more split, stage-colour active state, flyout panel |
| `resources/js/views/Dashboard.vue` | Journey progress hero, stage-curated card selection/ordering, goals card, life timeline card |
| `resources/js/components/Onboarding/OnboardingWizard.vue` | Stage-aware step selection, learning sidebar layout |
| `resources/js/components/Onboarding/FocusAreaSelection.vue` | Replace with life stage welcome screen + focus area shortcuts |
| `resources/js/store/modules/journeys.js` | **Full replacement** — life stages replace module-based journeys. Existing journey selections are deprecated. Store becomes the life stage state manager (current stage, progress, transitions) |
| `resources/js/store/modules/onboarding.js` | Stage-aware step progression |
| `resources/js/components/Preview/PersonaSelector.vue` | Group personas by stage |
| `resources/js/mobile/views/MobileDashboard.vue` | Stage-curated cards, journey progress |
| `resources/js/mobile/MobileTabBar.vue` | Stage-adaptive tab mapping |
| `database/seeders/PreviewUserSeeder.php` | Remove widow persona, amend young_saver, add life_stage field |
| `app/Http/Controllers/Api/PreviewController.php` | Remove `widow` from VALID_PERSONAS constant and persona metadata |
| `app/Console/Commands/ResetPreviewData.php` | Remove widow references |
| `app/Http/Controllers/Api/JourneyController.php` | Refactor endpoints to serve life stage data instead of module-based journeys |
| `resources/js/data/personas/young_saver.json` | Age up to ~28, expand financial data, add `life_stage` |
| `resources/js/data/personas/student.json` | Add `life_stage: "university"` |
| `resources/js/data/personas/young_family.json` | Add `life_stage: "mid_career"` |
| `resources/js/data/personas/entrepreneur.json` | Add `life_stage: "mid_career"` |
| `resources/js/data/personas/peak_earners.json` | Add `life_stage: "peak"` |
| `resources/js/data/personas/retired_couple.json` | Add `life_stage: "retirement"` |
| `resources/js/data/personas/widow.json` | Delete |
| `resources/js/components/UserProfile/PersonalInformation.vue` | Add `context` prop + stage-adaptive field visibility via `isFieldVisible()` |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Add `context` prop + stage-adaptive field visibility |
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Add `context` prop + stage-adaptive field visibility |
| `resources/js/components/Savings/SaveAccountModal.vue` | Add `context` prop + stage-adaptive defaults (account types, guidance text) |
| `resources/js/components/Protection/PolicyFormModal.vue` | Add `context` prop + stage-adaptive defaults |
| Landing page components | "Find Your Stage" interactive |

### 13.3 Files to Retire (Onboarding-Only Duplicates)

| File | Replaced By |
|------|------------|
| `resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue` | `PersonalInformation.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` | `PersonalInformation.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue` | `ExpenditureForm.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/ExpenditureStep.vue` | `ExpenditureForm.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue` | `PropertyForm.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue` | `SaveAccountModal.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/LiabilitiesStep.vue` | `LiabilityForm.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/FamilyInfoStep.vue` | `FamilyMemberFormModal.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/GoalSetupStep.vue` | `GoalFormModal.vue` with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | New unified income form with `context="onboarding"` |
| `resources/js/components/Onboarding/steps/QuickAssetsStep.vue` | Remove — assets entered via individual forms per step |
| `resources/js/components/Onboarding/steps/AssetsStep.vue` | Remove — assets entered via individual forms per step |

### 13.4 Existing Patterns to Preserve

- ApexCharts donut with `ASSET_COLORS` from `designSystem.js`
- `.card` CSS class (bg-white, rounded-card, border-light-gray, shadow-sm, p-6)
- 3-column grid (`lg:grid-cols-3 gap-3`)
- Plan-driven actions (max 2 per card, decision-engine plain English titles, no icons)
- Priority colours (raspberry for critical/high, violet for medium, spring for low)
- `savannah-100` hover on interactive rows
- `SideMenuIcon.vue` SVG icons
- All Tailwind tokens from `fynlaDesignGuide.md` v1.2.0

### 13.5 Design Guide Compliance

All new components must use:
- **Colours:** Raspberry (CTAs), Horizon (text/nav), Spring (success), Violet (info/warnings), Savannah (hover/subtle), Eggshell (page bg), Neutral (secondary text), Light-gray (borders)
- **Typography:** Segoe UI (primary), Inter (fallback), weights 900 (display/h1), 700 (h2-h5)
- **Banned:** amber-*, orange-*, primary-*, secondary-*, gray-* for general UI
- **No hardcoded hex** in style blocks — use Tailwind classes
- **Chart colours** via `designSystem.js` constants

### 13.6 Dynamic Promotion Logic

When a user adds data for a module not in their stage's primary sidebar list, that module is automatically promoted to the primary section.

**Mechanism:** A reactive computed property in a new `lifeStage` Vuex module evaluates user data flags against the stage config.

**Data source:** Extend the existing `assetFlags` pattern from `onboarding.js` into a `userDataFlags` getter in `store/modules/lifeStage.js`:

```javascript
userDataFlags: {
  properties: netWorthStore.properties.length > 0,
  savings: netWorthStore.savingsAccounts.length > 0,
  investments: netWorthStore.investmentAccounts.length > 0,
  pensions: retirementStore.pensions.length > 0,
  protection: protectionStore.policies.length > 0,
  will: estateStore.will !== null,
  trusts: estateStore.trusts.length > 0,
  business: netWorthStore.businessInterests.length > 0,
}
```

**Evaluation:** Computed on every route change (via router `afterEach` hook). The sidebar's `primaryItems` computed property merges:
1. `lifeStageConfig[stage].sidebar.primary` (static stage defaults)
2. Any module from `sidebar.explore` where `userDataFlags[module] === true` (dynamic promotions)

**Dashboard cards:** Same logic — `lifeStageConfig[stage].dashboard.cards` defines the potential set, but cards only render if the relevant Vuex store has data.

### 13.7 Stage Suggestion Algorithm

When `LifeStageService` detects a significant profile change (DOB update, new family member, marital status change), it evaluates whether to suggest a stage transition:

**Rule:** Suggest the next stage upward when the user's age exceeds the midpoint of their current stage's age range AND a qualifying life event has occurred.

| Current Stage | Suggest Transition When |
|---------------|------------------------|
| Starting Out | Age > 22 AND (first full-time job OR first property) |
| Building Foundations | Age > 29 AND (first child OR marriage OR property count > 1) |
| Protecting What Matters | Age > 48 AND (children independent OR pension value > £200k) |
| Planning Your Future | Age > 63 AND (retirement date set OR stopped working) |
| Enjoying Your Wealth | (terminal stage — no transition) |

Suggestions are shown at most once per 6 months per stage. User can dismiss permanently via "Stay Where I Am" + "Don't ask again" checkbox.

### 13.8 Journey Progress Calculation

**Formula:** Percentage of onboarding steps completed for the current stage.

```
progress = (completedSteps.length / lifeStageConfig[stage].onboarding.steps.length) * 100
```

Where `completedSteps` are steps with at least one non-empty data field saved. Skipped steps count as incomplete.

After onboarding is 100% complete, the progress bar transitions to showing **module data completeness** — percentage of primary sidebar modules that have at least one record.

### 13.9 Stage Config Cross-Reference

Each stage's full configuration is defined across these spec sections:

| Stage | Sidebar (§7.3) | Dashboard Cards (§8.2) | Journey Steps (§5.2) | Suggested Goals (§9.1) |
|-------|----------------|------------------------|----------------------|------------------------|
| `university` | 7 items | 5 cards | 6 steps | 4 goals |
| `early_career` | 8 items | 6 cards | 7 steps | 4 goals |
| `mid_career` | 8 items | 8 cards | 8 steps | 4 goals |
| `peak` | 8 items | 7 cards | 7 steps | 4 goals |
| `retirement` | 8 items | 7 cards | 6 steps | 4 goals |

These sections are the authoritative source for the data that populates each stage's `lifeStageConfig` object.

---

## 14. Visual Reference

Mockups created during the brainstorming session are available at:
`.superpowers/brainstorm/34262-1773732917/`

| File | Section |
|------|---------|
| `life-stage-config.html` | Configuration architecture |
| `welcome-screen-v2.html` | Welcome screen with heading options |
| `journey-preview-v6.html` | Meandering journey map (final) |
| `onboarding-flow.html` | Two-column onboarding with learning sidebar |
| `adaptive-sidebar.html` | Side-by-side student vs mid-career sidebars |
| `sidebar-collapsed.html` | Collapsed state with progress ring + flyout |
| `dashboard-v4.html` | Final dashboard with existing patterns + goals + timeline |
| `goals-life-events.html` | Stage-suggested goals, life event triggers, stage transition |
