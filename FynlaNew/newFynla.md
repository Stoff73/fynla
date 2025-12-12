# Fynla Preview Dashboard with Personas

## Product Requirements Document

**Document Version:** 1.0
**Date:** 11 December 2025
**Author:** Product Management
**Status:** Draft for Review

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Problem Analysis](#2-problem-analysis)
3. [User Personas (Preview Personas)](#3-user-personas-preview-personas)
4. [User Stories with Acceptance Criteria](#4-user-stories-with-acceptance-criteria)
5. [User Flows](#5-user-flows)
6. [Technical Requirements](#6-technical-requirements)
7. [UI/UX Specifications](#7-uiux-specifications)
8. [Edge Cases](#8-edge-cases)
9. [Dependencies](#9-dependencies)
10. [Acceptance Criteria Summary](#10-acceptance-criteria-summary)
11. [Open Questions](#11-open-questions)

---

## 1. Executive Summary

### Elevator Pitch

Let visitors explore a fully-functional financial planning dashboard with example data before signing up, so they can understand the value before committing.

### Problem Statement

Currently, users must register and complete onboarding before seeing Fynla's dashboard, creating a high barrier to entry. Users cannot evaluate whether the platform meets their needs until after they have invested significant time in registration and data entry.

### Target Audience

- **Primary:** UK individuals aged 30-65 considering financial planning tools
- **Secondary:** Couples wanting to understand joint financial planning capabilities
- **Tertiary:** High-net-worth individuals exploring estate planning features

### Unique Selling Proposition

Unlike competitors that require registration first, Fynla lets users experience the full dashboard with realistic UK financial scenarios, interact with calculations, and even keep the demo data as a starting point when they register.

### Success Metrics

| Metric | Current | Target | Measurement |
|--------|---------|--------|-------------|
| Landing page to registration conversion | ~2% | 8-12% | Analytics |
| Time on preview dashboard | N/A | >5 minutes | Analytics |
| Preview-to-registration conversion | N/A | 25%+ | Analytics |
| Users keeping demo data on registration | N/A | 40%+ | Database flag |
| Post-registration dashboard guidance completion | N/A | 70%+ | Completion tracking |
| Net Promoter Score (preview experience) | N/A | >40 | Survey |

---

## 2. Problem Analysis

### What specific problem does this solve?

1. **High friction onboarding:** Users must register and complete a 10-step wizard before seeing value
2. **Unclear value proposition:** Landing page describes features but does not demonstrate them
3. **Fear of commitment:** Users hesitate to enter personal financial data without understanding the tool
4. **Cognitive load:** New users must imagine how their data would look rather than seeing examples

### Who experiences this problem most acutely?

- **Time-poor professionals:** Want to evaluate quickly without long sign-up processes
- **Privacy-conscious users:** Reluctant to share data before understanding what happens to it
- **Comparison shoppers:** Evaluating multiple financial planning tools
- **Skeptical prospects:** Need proof of value before committing

### How are users currently solving this problem?

- Using the `demo@fps.com / password` credentials shown on landing page (requires login)
- Watching video demonstrations (if available)
- Reading documentation
- Signing up and abandoning if not meeting expectations

### What is the cost of the problem remaining unsolved?

- **Lost conversions:** Potential users bounce from landing page
- **Wasted development:** Features built but not discovered by users
- **Support burden:** Users register, struggle, then request help or delete accounts
- **Competitive disadvantage:** Competitors with "try before you buy" models win users

---

## 3. User Personas (Preview Personas)

These four personas represent different life stages AND financial complexity levels, showcasing different Fynla modules.

### Persona 1: Emily and James Carter - "The Young Family"

**Life Stage:** Early career couple with young children
**Primary Modules Showcased:** Protection, Savings, Basic Estate Planning

#### Demographics
- **Ages:** Emily (32), James (34)
- **Location:** Birmingham, UK
- **Occupation:** Emily - Marketing Manager (income: 48,000 GBP), James - Software Developer (income: 62,000 GBP)
- **Family:** Married, 2 children (ages 3 and 6)
- **Marital Status:** Married

#### Financial Snapshot

| Category | Details |
|----------|---------|
| **Property** | Main residence: 320,000 GBP value, 245,000 GBP mortgage |
| **Savings** | Emergency fund: 8,500 GBP, Children's savings: 4,200 GBP |
| **Investments** | S&S ISA: 15,000 GBP (just starting) |
| **Pensions** | Emily: DC 22,000 GBP, James: DC 45,000 GBP |
| **Protection** | Life insurance: 350,000 GBP term, No income protection |
| **Liabilities** | Mortgage: 245,000 GBP, Car loan: 12,000 GBP |
| **Net Worth** | Approximately 150,000 GBP |
| **Monthly Expenditure** | 4,200 GBP |

#### Key Concerns (What they want to explore)
- Do we have enough life insurance if something happens?
- How much should we have in our emergency fund?
- Are we saving enough for our children's education?
- When can we afford to move to a bigger house?

#### Protection Gap Highlights
- **Critical illness coverage:** None (gap identified)
- **Income protection:** None (gap identified)
- **Life insurance:** Coverage exists but adequacy score shows shortfall

#### Why This Persona?
- Demonstrates protection gap analysis
- Shows emergency fund calculations
- Illustrates basic family estate planning (wills, guardians)
- Relatable to many first-time users

---

### Persona 2: David and Sarah Mitchell - "The Peak Earners"

**Life Stage:** Mid-career high earners approaching peak wealth accumulation
**Primary Modules Showcased:** Investment, Retirement Planning, Tax Optimisation

#### Demographics
- **Ages:** David (48), Sarah (46)
- **Location:** Surrey, UK
- **Occupation:** David - Finance Director (income: 145,000 GBP), Sarah - GP Partner (income: 120,000 GBP)
- **Family:** Married, 2 children (ages 14 and 17, both in private school)
- **Marital Status:** Married

#### Financial Snapshot

| Category | Details |
|----------|---------|
| **Property** | Main residence: 850,000 GBP value, 280,000 GBP mortgage; BTL property: 425,000 GBP, 220,000 GBP mortgage |
| **Savings** | Cash ISAs: 45,000 GBP, Premium Bonds: 50,000 GBP |
| **Investments** | S&S ISAs: 180,000 GBP, GIA: 95,000 GBP, VCT: 30,000 GBP |
| **Pensions** | David: DB pension + DC SIPP 320,000 GBP, Sarah: NHS Pension (DB) |
| **Protection** | Life insurance: 500,000 GBP, Critical illness: 200,000 GBP |
| **Liabilities** | Mortgages: 500,000 GBP total, School fees loan: 25,000 GBP |
| **Net Worth** | Approximately 1,450,000 GBP |
| **Monthly Expenditure** | 9,500 GBP |

#### Key Concerns (What they want to explore)
- Are we on track for retirement at 60?
- How do we optimise our tax position with tapered pension allowance?
- Should we pay off the mortgage or invest more?
- What is the best strategy for the BTL property?

#### Investment Highlights
- **Portfolio allocation:** 65% equities, 20% bonds, 15% alternatives
- **Risk profile:** Balanced/Adventurous (score 72/100)
- **ISA utilisation:** Both maximising annual allowance

#### Why This Persona?
- Demonstrates complex investment portfolio analysis
- Shows pension projection and retirement readiness scoring
- Illustrates ISA allowance tracking across accounts
- Monte Carlo simulation relevance for retirement planning

---

### Persona 3: Margaret Thompson - "The Widow Planning Ahead"

**Life Stage:** Recently widowed retiree with estate planning focus
**Primary Modules Showcased:** Estate Planning, IHT Calculations, Will Planning

#### Demographics
- **Age:** 68
- **Location:** Cotswolds, UK
- **Occupation:** Retired (former headteacher, state pension + teacher's pension)
- **Family:** Widow (spouse passed 2 years ago), 3 adult children, 5 grandchildren
- **Marital Status:** Widowed

#### Financial Snapshot

| Category | Details |
|----------|---------|
| **Property** | Main residence: 625,000 GBP (mortgage-free), Holiday cottage: 285,000 GBP |
| **Savings** | Cash ISAs: 85,000 GBP, NS&I: 50,000 GBP |
| **Investments** | S&S ISA: 220,000 GBP, Offshore bond: 150,000 GBP, GIA: 180,000 GBP |
| **Pensions** | State pension: 11,502 GBP/year, Teacher's pension: 28,000 GBP/year |
| **Protection** | Whole of life: 100,000 GBP (in trust for IHT) |
| **Liabilities** | None |
| **Net Worth** | Approximately 1,595,000 GBP |
| **Monthly Expenditure** | 3,200 GBP |

#### Key Concerns (What they want to explore)
- How much IHT will my children have to pay?
- Should I gift money now or wait?
- Is my will up to date after my husband's passing?
- What is the best way to help grandchildren with house deposits?

#### Estate Planning Highlights
- **Gross estate value:** 1,595,000 GBP
- **Available NRB:** 650,000 GBP (own 325k + transferred spouse 325k)
- **Available RNRB:** 350,000 GBP (own 175k + transferred spouse 175k)
- **Current IHT liability estimate:** 238,000 GBP (40% of 595,000 GBP)
- **Gifts in last 7 years:** 45,000 GBP to children

#### Why This Persona?
- Demonstrates full IHT calculation with NRB/RNRB
- Shows spouse NRB/RNRB transfer mechanics
- Illustrates gift tracking and 7-year rule
- Will planning features with trust provisions
- Showcases IHT mitigation strategies

---

### Persona 4: Alex Chen - "The Entrepreneurial Single"

**Life Stage:** Single professional with business interests and growth focus
**Primary Modules Showcased:** Net Worth Overview, Protection (key person), Business Interests

#### Demographics
- **Age:** 38
- **Location:** Manchester, UK
- **Occupation:** Owner/Director of tech consultancy (income: 180,000 GBP drawings + dividends)
- **Family:** Single, no dependents, elderly parents
- **Marital Status:** Single

#### Financial Snapshot

| Category | Details |
|----------|---------|
| **Property** | City centre apartment: 380,000 GBP, 190,000 GBP mortgage |
| **Business** | Tech consultancy: 450,000 GBP estimated value (60% ownership) |
| **Savings** | Easy access: 25,000 GBP, Notice account: 40,000 GBP |
| **Investments** | SIPP: 185,000 GBP, S&S ISA: 95,000 GBP, GIA: 45,000 GBP |
| **Protection** | Life insurance: 200,000 GBP, Key person insurance: 500,000 GBP through business |
| **Liabilities** | Mortgage: 190,000 GBP, Director's loan to company: 35,000 GBP |
| **Net Worth (personal)** | Approximately 1,030,000 GBP |
| **Monthly Expenditure** | 5,500 GBP |

#### Key Concerns (What they want to explore)
- What happens to my business if something happens to me?
- How do I balance pension contributions vs. business investment?
- Should I buy more property or diversify investments?
- How do I plan for parents who may need care?

#### Business & Protection Highlights
- **Business succession:** No formal plan
- **Buy-sell agreement:** Not in place
- **Key person insurance:** Exists but no succession trigger
- **Shareholder protection:** Needed discussion

#### Why This Persona?
- Demonstrates business interests module
- Shows single-person estate planning (no spouse)
- Illustrates key person insurance concepts
- Net worth dashboard with business assets
- Different protection needs (no family dependents but business partners)

---

## 4. User Stories with Acceptance Criteria

### Epic 1: Preview Dashboard Access

#### US-1.1: Access Preview Dashboard from Landing Page

**As a** visitor on the landing page,
**I want to** click a button to see a fully-populated dashboard,
**So that** I can understand what Fynla offers without registering.

**Acceptance Criteria:**

```gherkin
Given I am on the landing page (/)
When I click "Try the Demo" or "Explore Dashboard" button
Then I am navigated to /preview-dashboard
And I see a fully-functional dashboard with example data
And I see a "Preview Mode" indicator in the header
And I am NOT required to log in or register
And all dashboard cards display populated data
```

**Additional Requirements:**
- Preview mode uses client-side state (no API calls that require auth)
- Default persona is loaded (Persona 1: Young Family)
- All navigation within preview mode is allowed
- Session persists until browser is closed or user registers

---

#### US-1.2: Preview Mode Visual Indicator

**As a** visitor in preview mode,
**I want to** clearly see that I am viewing example data,
**So that** I do not confuse it with my own data.

**Acceptance Criteria:**

```gherkin
Given I am viewing the preview dashboard
Then I see a persistent banner/indicator stating "Preview Mode - Example Data"
And the banner includes a "Register to Save Your Data" CTA
And the banner does not obscure dashboard content
And the indicator is visible on all preview pages
```

---

### Epic 2: Persona Selection

#### US-2.1: Switch Between Personas

**As a** visitor in preview mode,
**I want to** switch between different example personas,
**So that** I can see how Fynla handles different financial situations.

**Acceptance Criteria:**

```gherkin
Given I am on the preview dashboard
When I click the persona selector
Then I see 4 persona options with brief descriptions:
  | Persona | Description |
  | Emily & James | Young family - Protection & Savings focus |
  | David & Sarah | Peak earners - Investment & Retirement focus |
  | Margaret | Widow - Estate Planning & IHT focus |
  | Alex | Entrepreneur - Business & Net Worth focus |
And each persona shows a representative avatar/icon
And each persona shows key stats (age, net worth range, family status)

When I select a different persona
Then the dashboard reloads with that persona's data
And a brief loading indicator shows during transition
And the persona selector shows the currently selected persona
```

---

#### US-2.2: Persona Introduction Modal

**As a** visitor selecting a persona,
**I want to** see a brief introduction to the persona,
**So that** I understand their financial situation before exploring.

**Acceptance Criteria:**

```gherkin
Given I select a new persona from the selector
Then an introduction modal appears showing:
  - Persona name and brief bio
  - Key financial stats (net worth, income, property)
  - 3-4 key concerns/questions they want answered
  - "Explore [Persona Name]'s Dashboard" CTA button
When I click the CTA
Then the modal closes and I see the persona's dashboard
When I click outside the modal or press Escape
Then the modal closes and I see the persona's dashboard
```

---

### Epic 3: Interactive Editing (Allowed Fields)

#### US-3.1: Edit Numerical Values

**As a** visitor in preview mode,
**I want to** change numerical values (balances, amounts, contributions),
**So that** I can see how the calculations would work with different figures.

**Acceptance Criteria:**

```gherkin
Given I am viewing a detail page (e.g., pension detail, property detail)
And the field is a numerical field (balance, value, contribution)
When I click on an editable numerical field
Then an inline editor appears
And I can enter a new numerical value
When I save the change
Then the dashboard recalculates affected metrics
And I see updated values in related cards/charts
And the change is stored in session state (not persisted to database)
```

**Editable Numerical Fields:**
- Account balances (savings, investment, pension)
- Property values
- Mortgage balances
- Contribution amounts (monthly, annual)
- Policy sum assured amounts
- Annual income figures
- Monthly expenditure categories

---

#### US-3.2: Edit Percentage Values

**As a** visitor in preview mode,
**I want to** change percentage values (allocations, contributions),
**So that** I can see how different allocations affect outcomes.

**Acceptance Criteria:**

```gherkin
Given I am viewing a page with percentage fields
When I click on an editable percentage field
Then an inline editor appears with percentage formatting
And I can enter a new percentage value
When I save the change
Then affected calculations update
And related pie charts/allocations refresh
```

**Editable Percentage Fields:**
- Portfolio allocation percentages
- Ownership percentages (joint ownership)
- Pension contribution rates (employee/employer)
- Growth rate assumptions

---

#### US-3.3: Edit Date Values

**As a** visitor in preview mode,
**I want to** change date values (retirement age, policy dates),
**So that** I can see projections for different time horizons.

**Acceptance Criteria:**

```gherkin
Given I am viewing a page with date fields
When I click on an editable date field
Then a date picker appears
And I can select a new date
When I save the change
Then time-based projections recalculate
And affected charts (e.g., pension projection) update
```

**Editable Date Fields:**
- Target retirement age
- Policy end dates
- Mortgage end date
- Investment goal target dates

---

### Epic 4: Personal Information Edit Warning

#### US-4.1: Warning Modal on Personal Info Edit Attempt

**As a** visitor in preview mode,
**I want to** be warned when I try to edit personal information,
**So that** I understand this data will not be saved if I close the browser.

**Acceptance Criteria:**

```gherkin
Given I am in preview mode
And I click on a personal information field:
  - Name
  - Date of birth
  - Address
  - Email
  - Phone number
  - National Insurance number
  - Employment details (employer name, occupation)
  - Family member names
  - Beneficiary names
Then a warning modal appears with the message:
  Title: "Personal Information Will Not Be Saved"
  Message: "Personal details like names, dates of birth, and addresses
           are not saved in preview mode. Register now to create your
           own personalised financial plan."
And the modal offers two options:
  - "Register Now" (primary CTA button)
  - "Continue Without Saving" (secondary button with warning text)
And below the secondary button shows: "Warning: Any data you enter will be lost"
```

---

#### US-4.2: Continue Without Saving After Warning

**As a** visitor who wants to edit personal info anyway,
**I want to** proceed with editing despite the warning,
**So that** I can fully explore how the system works with my own details.

**Acceptance Criteria:**

```gherkin
Given the personal info warning modal is displayed
When I click "Continue Without Saving"
Then the modal closes
And I am taken to the edit form for the field I clicked
And I CAN edit the personal information
And changes are stored in session state only
And changes are lost when I close the browser or end the session
And I can continue using other features with my edited data
```

---

#### US-4.3: Register Now from Warning Modal

**As a** visitor who wants to save their changes,
**I want to** register directly from the warning modal,
**So that** I can start creating my own data.

**Acceptance Criteria:**

```gherkin
Given the personal info warning modal is displayed
When I click "Register Now"
Then I am navigated to the registration page
And the selected persona is remembered in session
And after registration I am asked about keeping demo data (US-5.2)
```

---

### Epic 5: Registration from Preview

#### US-5.1: Register Button in Preview Mode

**As a** visitor in preview mode,
**I want to** easily find the registration option,
**So that** I can create my account when ready.

**Acceptance Criteria:**

```gherkin
Given I am in preview mode
Then I see a "Register" button in the preview mode banner
And I see a "Register" option in the header navigation
When I click either registration option
Then I am navigated to the registration page
And my current persona selection is preserved in session
```

---

#### US-5.2: Keep Demo Data or Start Fresh Choice

**As a** new user who just registered after preview,
**I want to** choose whether to keep the demo data,
**So that** I can either have a starting point or begin fresh.

**Acceptance Criteria:**

```gherkin
Given I have just completed registration
And I was previously exploring preview mode
Then I see a modal with two options:
  Option 1: "Keep demo data as starting point"
    - Description: "Start with [Persona Name]'s example data.
      You can modify it to match your situation."
    - Shows summary: "X properties, X accounts, X policies will be added"
  Option 2: "Start fresh with my own information"
    - Description: "Begin with a clean slate and enter your own data."

When I select "Keep demo data"
Then my account is populated with the persona's data
And I see a confirmation message
And I am redirected to the dashboard

When I select "Start fresh"
Then my account remains empty
And I am redirected to the dashboard
```

---

### Epic 6: Post-Registration Dashboard Guidance

#### US-6.1: Skip Traditional Onboarding Wizard

**As a** newly registered user,
**I want to** go directly to the dashboard,
**So that** I can start using the product immediately.

**Acceptance Criteria:**

```gherkin
Given I have just completed registration
And I have made my "keep data or start fresh" choice
Then I am NOT redirected to the onboarding wizard (/onboarding)
Instead I am redirected to the dashboard (/dashboard)
And I see the dashboard with or without demo data (based on my choice)
```

---

#### US-6.2: First-Time Dashboard Guidance Modal

**As a** newly registered user on the dashboard,
**I want to** see guided prompts for next steps,
**So that** I know what to do to get value from Fynla.

**Acceptance Criteria:**

```gherkin
Given I am a newly registered user
And this is my first visit to the dashboard
Then I see a welcome modal overlay:
  Title: "Welcome to Fynla!"
  Message: "Let's personalise your financial plan. We'll guide you
           through the key sections step by step."
  CTA: "Let's Start"
  Secondary: "I'll explore on my own"

When I click "Let's Start"
Then the modal closes
And the first guidance tooltip appears

When I click "I'll explore on my own"
Then the modal closes
And no guidance appears
And I can manually access guidance later from settings
```

---

#### US-6.3: Step-by-Step Guidance Tooltips

**As a** newly registered user,
**I want to** see tooltips guiding me through key sections,
**So that** I complete my profile systematically.

**Acceptance Criteria:**

```gherkin
Given I clicked "Let's Start" on the welcome modal
Then I see the first guidance step:
  Step 1: Personal Information
    - Tooltip pointing to Profile card/link
    - Message: "Let's start by updating your personal information"
    - "Go to Profile" button

When I complete personal info and return to dashboard
Then I see the next guidance step:
  Step 2: Family Information (if married)
    - Tooltip pointing to Family section
    - Message: "Next, let's add your family members"

Subsequent steps in order:
  Step 3: Properties & Mortgages
  Step 4: Savings & Cash Accounts
  Step 5: Investment Accounts
  Step 6: Pension Information
  Step 7: Protection Policies
  Step 8: Income & Expenditure

Each step shows:
  - Current step number / total steps (e.g., "3 of 8")
  - Skip option ("Skip for now")
  - The tooltip highlights the relevant dashboard section
```

---

#### US-6.4: Guidance Progress Tracking

**As a** user going through guided setup,
**I want to** see my progress and skip/resume guidance,
**So that** I have control over the experience.

**Acceptance Criteria:**

```gherkin
Given I am in guided setup mode
Then I see a progress indicator showing completed/remaining steps
And I can skip any step with "Skip for now"
And skipped steps are tracked and can be returned to

When I leave the dashboard and return
Then guidance resumes from where I left off
Until all steps are completed or dismissed

When all guidance steps are complete
Then I see a completion message:
  "Great job! Your Fynla profile is set up.
   Explore your dashboard to see your financial overview."
And guidance mode is marked as complete in my profile
```

---

#### US-6.5: Re-access Guidance

**As a** user who dismissed or completed guidance,
**I want to** re-access the setup guide,
**So that** I can go through steps I skipped.

**Acceptance Criteria:**

```gherkin
Given I previously dismissed or completed guidance
When I go to Settings
Then I see an option "Re-run Setup Guide"
When I click this option
Then the guidance begins from the first incomplete section
```

---

## 5. User Flows

### Flow 1: New Visitor to Preview to Register to Dashboard with Guidance

```
[Landing Page]
    |
    v
[Click "Try the Demo"]
    |
    v
[Preview Dashboard - Persona 1 (Young Family)]
    |
    v
[Explore: Switch to Persona 3 (Margaret - Estate)]
    |
    +--[Edit some numerical values]
    |
    +--[Try to edit name -> Warning Modal]
    |
    v
[Click "Register Now" from banner]
    |
    v
[Registration Page]
    |
    v
[Complete Registration Form]
    |
    v
["Keep demo data or Start fresh?" Modal]
    |
    +--[Keep Margaret's data]----+
    |                             |
    +--[Start fresh]----+        |
                        |        |
                        v        v
                   [Dashboard]
                        |
                        v
               [Welcome Modal - "Let's Start"]
                        |
                        v
               [Step 1: Personal Info Tooltip]
                        |
                        v
               [User clicks "Go to Profile"]
                        |
                        v
               [Profile Page - Edit Info]
                        |
                        v
               [Return to Dashboard]
                        |
                        v
               [Step 2: Family Info Tooltip]
                        |
                       ...
               [Steps 3-8]
                        |
                        v
               [Completion Message]
                        |
                        v
               [Regular Dashboard Use]
```

### Flow 2: New Visitor to Preview to Personal Info Warning to Register

```
[Landing Page]
    |
    v
[Click "Try the Demo"]
    |
    v
[Preview Dashboard - Default Persona]
    |
    v
[Navigate to Profile Section]
    |
    v
[Click on "Name" field to edit]
    |
    v
[Warning Modal Appears]
    |
    +--["Continue Exploring"]---> [Modal Closes, Stays on Profile]
    |
    +--["Register Now"]
            |
            v
    [Registration Page]
            |
            v
    [Complete Registration]
            |
            v
    ["Keep data or Start fresh?" Modal]
            |
            v
    [Dashboard with Guidance]
```

### Flow 3: Returning Registered User (No Preview History)

```
[Landing Page]
    |
    v
[Click "Sign In"]
    |
    v
[Login Page - Enter Credentials]
    |
    v
[Dashboard]
    |
    +--[If first login AND onboarding not complete]
    |       |
    |       v
    |   [Welcome Modal with Guidance Option]
    |
    +--[If returning user with complete profile]
            |
            v
        [Regular Dashboard - No Guidance]
```

### Flow 4: Visitor Explores Multiple Personas Then Registers

```
[Landing Page]
    |
    v
[Click "Try the Demo"]
    |
    v
[Preview Dashboard - Persona 1]
    |
    v
[Click Persona Selector]
    |
    v
[Select Persona 2 (Peak Earners)]
    |
    v
[Persona Introduction Modal]
    |
    v
[Explore Dashboard - Investment Focus]
    |
    v
[Select Persona 4 (Entrepreneur)]
    |
    v
[Explore Dashboard - Business Focus]
    |
    v
[Click "Register" in Banner]
    |
    v
[Registration Page]
    |
    v
["Keep demo data or Start fresh?"]
    |
    |   Note: Offers to keep LAST SELECTED persona (Alex - Entrepreneur)
    |
    v
[Make Selection]
    |
    v
[Dashboard with Guidance]
```

---

## 6. Technical Requirements

### 6.1 Frontend Components Required

#### New Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `PreviewDashboard.vue` | `views/Preview/` | Main preview dashboard wrapper |
| `PreviewBanner.vue` | `components/Preview/` | Persistent "Preview Mode" indicator |
| `PersonaSelector.vue` | `components/Preview/` | Dropdown/modal for persona switching |
| `PersonaIntroModal.vue` | `components/Preview/` | Introduction modal when switching personas |
| `PersonalInfoWarningModal.vue` | `components/Preview/` | Warning when editing restricted fields |
| `KeepDataOrFreshModal.vue` | `components/Onboarding/` | Post-registration choice modal |
| `DashboardGuidance.vue` | `components/Dashboard/` | Step-by-step tooltip guidance system |
| `GuidanceTooltip.vue` | `components/Dashboard/` | Individual guidance tooltip component |
| `GuidanceProgress.vue` | `components/Dashboard/` | Progress indicator for guidance |

#### Modified Components

| Component | Changes Required |
|-----------|------------------|
| `LandingPage.vue` | Add "Try the Demo" CTA button |
| `Dashboard.vue` | Support guidance mode, check for first-time user |
| `Register.vue` | Handle post-preview registration flow |
| `AppLayout.vue` | Conditionally show preview banner |
| `router/index.js` | Add preview routes, modify post-registration redirect |
| All form components | Add preview mode checks for restricted fields |

### 6.2 State Management Approach

#### New Vuex Module: `preview.js`

```javascript
// store/modules/preview.js
const state = {
  isPreviewMode: false,
  currentPersona: null, // 'young_family' | 'peak_earners' | 'widow' | 'entrepreneur'
  personaData: {}, // Loaded persona data (from JSON files)
  editedValues: {}, // User modifications in preview

  // Personas metadata
  personas: [
    {
      id: 'young_family',
      name: 'Emily & James Carter',
      tagline: 'Young Family',
      focus: 'Protection & Savings',
      netWorthRange: '100k - 200k',
      icon: 'family',
    },
    // ... other personas
  ],
};

const getters = {
  isPreviewMode: (state) => state.isPreviewMode,
  currentPersona: (state) => state.currentPersona,
  currentPersonaData: (state) => state.personaData[state.currentPersona] || null,
  // Merge base persona data with user edits
  effectiveData: (state) => {
    const base = state.personaData[state.currentPersona] || {};
    return deepMerge(base, state.editedValues);
  },
};

const actions = {
  enterPreviewMode({ commit }, personaId = 'young_family') {
    commit('SET_PREVIEW_MODE', true);
    commit('SET_CURRENT_PERSONA', personaId);
    // Load persona data from static JSON
  },

  exitPreviewMode({ commit }) {
    commit('SET_PREVIEW_MODE', false);
    commit('CLEAR_EDITED_VALUES');
  },

  switchPersona({ commit }, personaId) {
    commit('SET_CURRENT_PERSONA', personaId);
    commit('CLEAR_EDITED_VALUES');
  },

  updatePreviewValue({ commit }, { path, value }) {
    commit('SET_EDITED_VALUE', { path, value });
  },
};
```

#### New Vuex Module: `guidance.js`

```javascript
// store/modules/guidance.js
const state = {
  isGuidanceActive: false,
  currentStep: 0,
  completedSteps: [],
  skippedSteps: [],
  totalSteps: 8,

  steps: [
    { id: 'personal_info', label: 'Personal Information', target: 'profile-link' },
    { id: 'family_info', label: 'Family Members', target: 'family-section' },
    { id: 'properties', label: 'Properties & Mortgages', target: 'property-card' },
    { id: 'savings', label: 'Savings & Cash', target: 'savings-card' },
    { id: 'investments', label: 'Investment Accounts', target: 'investment-card' },
    { id: 'pensions', label: 'Pension Information', target: 'retirement-card' },
    { id: 'protection', label: 'Protection Policies', target: 'protection-card' },
    { id: 'income', label: 'Income & Expenditure', target: 'expenditure-link' },
  ],
};
```

### 6.3 Data Storage Approach

#### Persona Data Files

Store persona data as static JSON files loaded on demand. **Critical:** Persona JSON files must match the EXACT database/API schema - identical to what a real user's data looks like. This enables:
- Direct reuse of all existing Vue components without modification
- Persona data can be directly seeded to database when user chooses "keep data"
- Real calculation services work out-of-the-box
- Zero transformation code needed

```
resources/js/data/personas/
  young_family.json
  peak_earners.json
  widow.json
  entrepreneur.json
```

**Structure mirrors database schema exactly:**

```json
{
  "user": {
    "name": "James Carter",
    "email": "james.carter@example.com",
    "date_of_birth": "1991-03-15",
    "gender": "male",
    "marital_status": "married",
    "employment_status": "employed",
    "annual_employment_income": 62000,
    "occupation": "Software Developer",
    "employer": "TechCorp Ltd",
    "address_line_1": "42 Oak Avenue",
    "city": "Birmingham",
    "postcode": "B15 2TT",
    "phone": "07700 900123",
    "national_insurance_number": "AB123456C",
    "country_of_birth": "United Kingdom",
    "is_uk_domiciled": true
  },
  "spouse": {
    "name": "Emily Carter",
    "date_of_birth": "1993-06-22",
    "gender": "female",
    "annual_employment_income": 48000,
    "occupation": "Marketing Manager",
    "employer": "MarketPro Agency"
  },
  "family_members": [
    {
      "name": "Oliver Carter",
      "relationship": "child",
      "date_of_birth": "2019-04-10",
      "is_dependant": true
    }
  ],
  "properties": [
    {
      "address_line_1": "42 Oak Avenue",
      "city": "Birmingham",
      "postcode": "B15 2TT",
      "property_type": "main_residence",
      "ownership_type": "joint",
      "ownership_percentage": 50,
      "current_value": 320000,
      "purchase_price": 285000,
      "purchase_date": "2018-06-15"
    }
  ],
  "mortgages": [
    {
      "lender_name": "Nationwide",
      "original_amount": 256000,
      "current_balance": 245000,
      "interest_rate": 4.25,
      "rate_type": "fixed",
      "mortgage_type": "repayment",
      "term_years": 25,
      "monthly_payment": 1380,
      "start_date": "2018-06-15",
      "end_date": "2043-06-15"
    }
  ],
  "savings_accounts": [...],
  "investment_accounts": [...],
  "holdings": [...],
  "dc_pensions": [...],
  "db_pensions": [...],
  "state_pensions": [...],
  "life_insurance_policies": [...],
  "critical_illness_policies": [...],
  "income_protection_policies": [...],
  "liabilities": [...],
  "expenditure": {...},
  "will": {...},
  "trusts": [...],
  "gifts": [...]
}
```

**Note:** All field names, data types, and enums must match exactly what the API returns for authenticated users. Use `ComprehensiveDemoDataSeeder.php` as the reference template.

### 6.4 API Changes

#### Preview Mode API Strategy

Preview mode uses REAL backend calculation services to provide the full user experience. The approach:

1. **Calculation endpoints** accept preview data payloads and return real calculations
2. **No data persistence** - preview data is never saved to database
3. **No authentication required** - preview endpoints are public

**New preview-specific endpoints:**

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `POST /api/preview/calculate-iht` | POST | IHT calculation with preview estate data |
| `POST /api/preview/calculate-protection-gaps` | POST | Protection gap analysis with preview data |
| `POST /api/preview/calculate-net-worth` | POST | Net worth calculation with preview assets/liabilities |
| `POST /api/preview/calculate-retirement-projection` | POST | Pension projections with preview data |
| `POST /api/preview/calculate-emergency-fund` | POST | Emergency fund analysis with preview data |

These endpoints mirror existing authenticated endpoints but:
- Accept data in request body (not from database)
- Return calculations without persisting anything
- Are publicly accessible (no auth required)

#### Modified endpoints for guidance:

| Endpoint | Change |
|----------|--------|
| `POST /api/user/guidance-status` | New - Track guidance progress |
| `GET /api/user/guidance-status` | New - Retrieve guidance state |
| `PUT /api/user` | Add `guidance_completed` flag |

### 6.5 Route Changes

```javascript
// router/index.js additions

// New public preview routes
{
  path: '/preview',
  name: 'PreviewDashboard',
  component: () => import('@/views/Preview/PreviewDashboard.vue'),
  meta: { public: true, previewMode: true },
},
{
  path: '/preview/net-worth',
  name: 'PreviewNetWorth',
  component: () => import('@/views/Preview/PreviewNetWorth.vue'),
  meta: { public: true, previewMode: true },
},
{
  path: '/preview/protection',
  name: 'PreviewProtection',
  component: () => import('@/views/Preview/PreviewProtection.vue'),
  meta: { public: true, previewMode: true },
},
// ... other preview routes

// Modified registration redirect logic
// In Register.vue or auth store:
// After registration, if coming from preview:
//   1. Show KeepDataOrFreshModal
//   2. Redirect to /dashboard (not /onboarding)
//   3. Set guidance_active = true
```

### 6.6 Database Changes

#### Migration 1: New columns on `users` table

```php
// Migration: add_guidance_and_preview_columns_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    // Guidance tracking
    $table->boolean('guidance_active')->default(false);
    $table->boolean('guidance_completed')->default(false);
    $table->integer('guidance_current_step')->default(0);
    $table->json('guidance_completed_steps')->nullable();
    $table->json('guidance_skipped_steps')->nullable();
    $table->string('guidance_version')->nullable(); // Reinitialise on major updates/tax changes

    // Registration source tracking
    $table->string('registration_source')->nullable(); // 'preview' | 'direct' | null
    $table->string('preview_persona_kept')->nullable(); // Which persona data was kept
});
```

#### Migration 2: Add `is_demo_origin` flag to asset/liability tables

To track which records originated from demo persona data (invisible to user):

```php
// Migration: add_demo_origin_flag_to_financial_tables.php

// Tables requiring the flag:
$tables = [
    'properties',
    'mortgages',
    'savings_accounts',
    'investment_accounts',
    'holdings',
    'dc_pensions',
    'db_pensions',
    'state_pensions',
    'life_insurance_policies',
    'critical_illness_policies',
    'income_protection_policies',
    'disability_policies',
    'sickness_illness_policies',
    'liabilities',
    'trusts',
    'gifts',
    'family_members',
];

foreach ($tables as $table) {
    Schema::table($table, function (Blueprint $table) {
        $table->boolean('is_demo_origin')->default(false)->after('user_id');
    });
}
```

**Purpose:** Allows analytics and support to distinguish user-entered data from demo-origin data without exposing this to the user.

---

## 7. UI/UX Specifications

### 7.1 Persona Selector Design

**Location:** Top-right of preview dashboard, below header

**Collapsed State:**
```
+--------------------------------------------------+
| [Avatar] Emily & James Carter  v                 |
|          Young Family                             |
+--------------------------------------------------+
```

**Expanded State (Dropdown/Modal):**
```
+--------------------------------------------------+
| SELECT A FINANCIAL SCENARIO                       |
+--------------------------------------------------+
| [x] Emily & James Carter                         |
|     Young Family                                  |
|     Net worth: 100k-200k | Protection Focus       |
+--------------------------------------------------+
| [ ] David & Sarah Mitchell                       |
|     Peak Earners                                  |
|     Net worth: 1.4M+ | Investment Focus           |
+--------------------------------------------------+
| [ ] Margaret Thompson                            |
|     Widow Planning Ahead                          |
|     Net worth: 1.5M+ | Estate Planning Focus      |
+--------------------------------------------------+
| [ ] Alex Chen                                    |
|     Entrepreneurial Single                        |
|     Net worth: 1M+ | Business & Growth Focus      |
+--------------------------------------------------+
```

**Styling:**
- Use Fynla brand colours (primary-600 for selected)
- Avatar icons: Family, Couple, Single Woman, Single Man
- Hover state shows brief description
- Selected state shows checkmark

### 7.2 Preview Mode Banner Design

**Position:** Fixed banner below main header, above content

```
+------------------------------------------------------------------+
| [i] PREVIEW MODE - Viewing example data for Emily & James Carter  |
|                                                                    |
|     Edit numerical values to see how calculations change.          |
|     [Register Now] to save your own financial plan.                |
+------------------------------------------------------------------+
```

**Styling:**
- Background: `bg-amber-50` with `border-b border-amber-200`
- Icon: Information icon in `text-amber-600`
- Text: `text-amber-800`
- CTA Button: `bg-primary-600 text-white` pill button
- Height: Compact (48px)
- Dismissible: No (always visible in preview mode)

### 7.3 Personal Info Warning Modal Design

```
+------------------------------------------+
|                                          |
|   [!]  Personal Information Will Not     |
|        Be Saved                          |
|                                          |
|   Personal details like names, dates     |
|   of birth, and addresses are not        |
|   saved in preview mode.                 |
|                                          |
|   Register now to create your own        |
|   personalised financial plan.           |
|                                          |
|   +----------------------------------+   |
|   |        Register Now              |   |  <- Primary CTA
|   +----------------------------------+   |
|                                          |
|   +----------------------------------+   |
|   |    Continue Without Saving       |   |  <- Secondary button
|   +----------------------------------+   |
|                                          |
|   [!] Warning: Any data you enter        |
|       will be lost                       |
|                                          |
+------------------------------------------+
```

**Styling:**
- Modal: White background with rounded corners, shadow-xl
- Warning icon: Amber exclamation in circle
- Title: Bold, `text-lg font-semibold`
- Primary CTA: Full-width `bg-primary-600` button
- Secondary button: Full-width `bg-gray-100 text-gray-700` button
- Warning text: Small text `text-sm text-amber-600` with warning icon
- Max-width: 400px
- Backdrop: Semi-transparent dark overlay

### 7.4 Keep Data or Start Fresh Modal Design

```
+--------------------------------------------------+
|                                                  |
|   Welcome to Fynla!                              |
|                                                  |
|   You were exploring Margaret Thompson's         |
|   example data. Would you like to keep it?       |
|                                                  |
|   +------------------------------------------+   |
|   | [House icon]                             |   |
|   | Keep Margaret's data as a starting point |   |
|   |                                          |   |
|   | Start with example properties, accounts, |   |
|   | and policies. Modify them to match your  |   |
|   | situation.                               |   |
|   |                                          |   |
|   | Summary:                                 |   |
|   | - 2 properties                           |   |
|   | - 5 accounts                             |   |
|   | - 1 protection policy                    |   |
|   +------------------------------------------+   |
|                                                  |
|   +------------------------------------------+   |
|   | [Sparkles icon]                          |   |
|   | Start fresh with my own information      |   |
|   |                                          |   |
|   | Begin with a clean slate. Enter your own |   |
|   | data step by step.                       |   |
|   +------------------------------------------+   |
|                                                  |
+--------------------------------------------------+
```

**Styling:**
- Two large clickable cards
- Selected state: Border changes to `border-primary-500` with `bg-primary-50`
- Icons: Relevant icons for each option
- Summary section: Small text showing what will be imported

### 7.5 Guided Tour/Modal Overlay Design

**Welcome Modal:**
```
+--------------------------------------------------+
|                                                  |
|   [Celebration icon]                             |
|                                                  |
|   Welcome to Fynla!                              |
|                                                  |
|   Let's personalise your financial plan.         |
|   We'll guide you through the key sections       |
|   step by step.                                  |
|                                                  |
|   +------------------------------------------+   |
|   |            Let's Start                   |   |
|   +------------------------------------------+   |
|                                                  |
|        I'll explore on my own                    |
|                                                  |
+--------------------------------------------------+
```

**Guidance Tooltip:**
```
                    +--------------------------------+
                    | Step 1 of 8                    |
                    |                                |
                    | Let's update your personal     |
                    | information first.             |
                    |                                |
                    | [Go to Profile]  [Skip]        |
                    +--------------------------------+
                              |
                              v
+---------------------------[*]---------------------------+
|  [Profile Card or Link - Highlighted]                   |
+---------------------------------------------------------+
```

**Styling:**
- Tooltip: White background, shadow-lg, rounded corners
- Arrow: Points to highlighted element
- Highlight: Target element gets `ring-2 ring-primary-500` with slight pulsing animation
- Progress: "Step X of Y" in small text
- Buttons: Primary "Go to X" button, secondary "Skip" text link

### 7.6 Visual Indicators for Field Types in Preview Mode

**Standard Editable Fields (numbers, percentages, dates):**
- Normal appearance
- On hover: Subtle blue outline `hover:ring-1 hover:ring-primary-200`
- Cursor: `cursor-pointer`
- Title attribute: "Click to edit"

**Personal Information Fields (names, DOB, addresses, etc.):**
- Normal appearance with subtle amber indicator
- Small info icon `[i]` next to field label in `text-amber-500`
- On hover: Subtle amber outline `hover:ring-1 hover:ring-amber-200`
- Cursor: `cursor-pointer`
- Title attribute: "Click to edit (changes not saved)"
- On click: Opens warning modal first, then allows editing if user continues

---

## 8. Edge Cases

### 8.1 Browser/Session Handling

| Scenario | Expected Behaviour |
|----------|-------------------|
| User closes browser during preview | Session lost, must start fresh |
| User opens preview in new tab | New preview session starts |
| User bookmarks preview URL | Preview loads with default persona |
| User shares preview URL | Recipient sees default persona |
| User is logged in and visits preview | Show message: "You already have an account. View your dashboard or explore preview." |

### 8.2 Registration Edge Cases

| Scenario | Expected Behaviour |
|----------|-------------------|
| User registers with same email as existing account | Normal "email exists" error |
| User abandons registration after starting | Preview session retained, can continue |
| User completes registration, then logs out immediately | Guidance state preserved for next login |
| User tries to access preview after logging in | Redirect to dashboard with message |

### 8.3 Data Edge Cases

| Scenario | Expected Behaviour |
|----------|-------------------|
| User edits value to invalid number (negative, NaN) | Validation prevents save, shows error |
| User edits percentage to >100% | Validation prevents save (where applicable) |
| User switches persona after making edits | Warning: "Unsaved changes will be lost. Continue?" |
| User edits then tries to register | Edits preserved and offered in "keep data" option |

### 8.4 Guidance Edge Cases

| Scenario | Expected Behaviour |
|----------|-------------------|
| User completes profile via different path | Guidance detects completion, moves to next step |
| User skips all steps | Guidance marked complete after all skipped |
| User starts guidance, does not finish, returns in 7+ days | Guidance resumes from last position |
| User on mobile/tablet | Guidance tooltips position responsively |

### 8.5 Persona-Specific Edge Cases

| Scenario | Expected Behaviour |
|----------|-------------------|
| User keeps "Margaret" data but is male | Allow it - user can edit after |
| User keeps "Married" persona data but is single | Allow it - user can modify family status |
| User wants to combine data from multiple personas | Not supported - must choose one or start fresh |

---

## 9. Dependencies

### 9.1 Technical Dependencies

| Dependency | Status | Notes |
|------------|--------|-------|
| Vue.js 3 | Existing | Current framework |
| Vuex | Existing | State management |
| Vue Router | Existing | Routing |
| TailwindCSS | Existing | Styling |
| ApexCharts | Existing | Charts |

### 9.2 Content Dependencies

| Item | Owner | Status |
|------|-------|--------|
| Persona 1 data (Young Family) | Product | To be created |
| Persona 2 data (Peak Earners) | Product | To be created |
| Persona 3 data (Widow) | Product | To be created |
| Persona 4 data (Entrepreneur) | Product | To be created |
| Persona avatars/icons | Design | To be created |
| Guidance copy (8 steps) | Product | To be created |
| Modal copy (all modals) | Product | To be created |

### 9.3 Existing System Dependencies

| System | Impact |
|--------|--------|
| Authentication | Must allow unauthenticated access to preview routes |
| Dashboard components | Must support data injection from preview store |
| Form components | Must check preview mode before showing edit UI |
| Net Worth calculations | Must work with preview data structure |
| Protection gap analysis | Must work with preview data |
| IHT calculations | Must work with preview data |

### 9.4 Feature Dependencies

| This Feature | Depends On |
|--------------|------------|
| Preview Dashboard | Existing Dashboard.vue structure |
| Persona data | Existing ComprehensiveDemoDataSeeder as template |
| Guidance system | User profile/settings infrastructure |
| Keep data feature | Data import/seeding capability |

---

## 10. Acceptance Criteria Summary

### Must Have (P0)

- [ ] Visitor can access preview dashboard from landing page without registration
- [ ] Preview dashboard displays fully populated example data
- [ ] 4 distinct personas are available with different financial situations
- [ ] User can switch between personas
- [ ] Numerical values (balances, amounts) are editable in preview
- [ ] Editing personal info shows warning modal with registration CTA
- [ ] "Continue Exploring" option closes modal without changes
- [ ] Registration from preview offers "keep data or start fresh" choice
- [ ] Post-registration redirects to dashboard (not onboarding wizard)
- [ ] First-time dashboard visit shows welcome modal with guidance option
- [ ] Guidance tooltips appear step-by-step when activated

### Should Have (P1)

- [ ] Persona introduction modal shows when switching personas
- [ ] Percentage and date values are editable in preview
- [ ] Edited values persist during preview session
- [ ] Guidance progress is tracked and persists across sessions
- [ ] User can skip guidance steps
- [ ] User can re-access guidance from settings
- [ ] Preview banner clearly indicates "example data"

### Nice to Have (P2)

- [ ] Persona selector shows summary stats
- [ ] Tooltips animate to highlight target elements
- [ ] Preview session survives page refresh (localStorage)
- [ ] Analytics track persona selection and feature usage
- [ ] A/B test: preview flow vs. direct registration

---

## 11. Stakeholder Decisions

### Questions Requiring Stakeholder Input - RESOLVED

1. **Persona balance:**
   - **Decision:** Start with 4 personas. May add more in future iterations based on user feedback.

2. **Data accuracy:**
   - **Decision:** Data MUST be extremely accurate, professional grade, and comply with all FCA regulations. Must meet the highest financial advice standards. All persona data will be reviewed and verified before release.
   - **Requirement:** Engage qualified financial adviser to review all persona data.

3. **Mobile experience:**
   - **Decision:** Both mobile and desktop must offer a professional, trustworthy, emotion-grabbing experience. Many younger users will use mobile; many older users will use desktop. Both platforms are equally important.
   - **Requirement:** Design mobile-first responsive UI. Tooltips become bottom sheets on mobile. Persona selector becomes full-screen modal on mobile.

4. **International users:**
   - **Decision:** Multi-currency and multi-country rules, decision trees, and logic will be built in a later phase. For now, focus on UK only.
   - **Future scope:** International expansion planned.

5. **Demo data retention:**
   - **Decision:** Demo data and user data must NOT be mixed. If user keeps persona data, it must be separately identified from actual user data in the database, but this distinction should be invisible to the user.
   - **Implementation:** Add `is_demo_origin` boolean flag to relevant tables. User never sees this flag.

6. **Spouse account:**
   - **Decision:** Give the user the choice. When keeping married persona data, ask if they want to create a spouse account or just their own.

7. **Guidance persistence:**
   - **Decision:** Guidance persists until user explicitly dismisses it. On all major updates and tax year changes, guidance reinitialises to inform users of changes.
   - **Implementation:** Track `guidance_version` to detect when to reinitialise.

8. **Preview analytics:**
   - **Decision:** Track:
     - Which modules users explore
     - Which personas users select
     - Any value changes users make
     - Time spent in each section
     - Conversion points (when they click Register)

### Technical Questions - RESOLVED

1. **State persistence:**
   - **Decision:** Session only. Data disappears on browser refresh, browser close, or disconnect. No localStorage persistence.
   - **Rationale:** Keeps preview truly temporary and encourages registration.

2. **Calculation services:**
   - **Decision:** Use REAL backend calculation services. Users get the full experience with accurate calculations, even though data is not persistent.
   - **Implementation:** Preview mode will call the same API endpoints but with preview data payload. Backend returns calculations without persisting.

3. **Route structure:**
   - **Decision:** Use mirrored structure (`/preview/net-worth`) as it:
     - Creates cleaner URL patterns
     - Easier to maintain (clear separation)
     - Less technical debt (single route guard checks `preview` prefix)
     - Simpler to add new preview routes in future
   - **Implementation:** All preview routes under `/preview/*` namespace.

4. **Data format:**
   - **Decision:** Persona data should be treated exactly the same as user data - identical format to API responses. No differences in structure.
   - **Rationale:**
     - Enables reuse of all existing components without modification
     - Persona data can be directly seeded to database when user chooses "keep data"
     - Real calculations work out-of-the-box
     - Zero transformation code needed
   - **Implementation:** Persona JSON files match exact database/API schema.

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 11 Dec 2025 | Product Management | Initial draft |

---

*This document was generated by Claude Code as part of product planning for Fynla v0.3.0*
