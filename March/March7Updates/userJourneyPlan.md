# Onboarding Redesign — User Journey Plan

**Date:** 7 March 2026 | **Version:** 1.0
**Scope:** Redesign onboarding from single-path flow to focus-area-driven user journeys
**Status:** Planning (pre-implementation)

---

## Executive Summary

Replace the current onboarding (single Quick Setup or 11-step Full Setup) with a focus-area-driven system where users choose which financial planning areas matter to them. Each selection creates a tailored mini-journey collecting only the data needed for that area. The dashboard becomes the central hub, showing journey progress, prompting completion, and celebrating finished journeys.

---

## 1. Current State Analysis

### What Exists Today

| Component | File | Purpose |
|-----------|------|---------|
| Welcome page | `FocusAreaSelection.vue` | "Welcome [name]" + Quick Setup (3 steps) or Full Setup link |
| Step wizard | `OnboardingWizard.vue` | Progress bar + step navigation |
| 13 step components | `Onboarding/steps/*.vue` | PersonalInfo, Income, QuickAssets, Family, Expenditure, Assets, Liabilities, Protection, Will, Trust, Domicile, Completion |
| Data requirements | `ModuleDataRequirementsService.php` | Defines fields per module with "why" explanations |
| Dashboard cards | `ProfileCompletionCards.vue` | Shows completion prompts for quick-onboarded users |
| State management | `store/modules/onboarding.js` | Tracks single focusArea, steps, progress, skippedSteps |
| Backend service | `OnboardingService.php` | Step ordering, progress tracking, skip logic |

### Problems With Current Approach

1. **Single path** — Quick Setup asks the same 3 things regardless of what the user cares about
2. **Full Setup overwhelming** — 11 steps is too many for users who only care about 1-2 areas
3. **No context** — Users don't know WHY they're providing each piece of information
4. **Trial card clutter** — 7-day trial notification on the welcome page adds noise at a critical moment
5. **No journey ownership** — Users can't see their progress per financial area

---

## 2. Design: Welcome Page

### Layout (Top to Bottom)

1. **Welcome Header** — "Welcome [first name]" with Fynla logo. "Thank you for registering with Fynla!" in raspberry-500. *Keep as-is.*

2. **Remove** — The entire "Your Free Trial" violet card (7-day trial, 30-day window). This information belongs in settings or a subtle banner, not the first thing users see after registering.

3. **Focus Area Section** — New. "What would you like to focus on?" heading. Subtext: "Choose one or more areas — we'll only ask for the information you need."

4. **4x2 Grid** — 8 focus area cards (see Section 3).

5. **Dynamic Preview** — Appears below the grid when selections are made (see Section 4).

6. **Action Buttons**:
   - Primary: "Start Your Journey" (raspberry-500 CTA, disabled until at least 1 selection)
   - Secondary: "Skip to Dashboard" (text link, neutral-500)

### Responsive Behaviour

| Screen | Grid | Cards |
|--------|------|-------|
| Desktop (1024px+) | 4 columns x 2 rows | Full card with icon + heading |
| Tablet (768-1023px) | 3 columns x 3 rows (last row has 2) | Same as desktop |
| Mobile (< 768px) | 2 columns x 4 rows | Compact card, smaller icon |

---

## 3. Design: Focus Area Grid

### The 8 Focus Areas

| # | Focus Area | Icon | Colour | Maps To Module |
|---|-----------|------|--------|----------------|
| 1 | Budgeting | Wallet/Calculator | `spring-500` | Expenditure + Savings |
| 2 | Protection | Shield | `horizon-500` | Protection module |
| 3 | Investment | TrendingUp | `raspberry-500` | Investment module |
| 4 | Retirement | Clock/Sunset | `violet-500` | Retirement module |
| 5 | Estate Planning | FileText/Scroll | `horizon-600` | Estate module |
| 6 | Family | Users/Heart | `raspberry-400` | Family + Spouse |
| 7 | Business | Briefcase | `spring-600` | Business Interests |
| 8 | Goal Tracking | Target/Flag | `violet-600` | Goals module |

### Card Design

Each card is a small toggle card (not a button that navigates):
- **Unselected**: `bg-white border border-light-gray` with muted icon
- **Selected**: `bg-savannah-100 border-2 border-raspberry-500` with coloured icon + checkmark
- **Hover**: `border-horizon-300 shadow-sm`
- Content: Icon (40x40 rounded circle) + heading text only. No descriptions on the cards themselves.

### Selection Behaviour

- Click/tap toggles selection (multi-select, not radio)
- Minimum 1 selection required to proceed (CTA disabled at 0)
- No maximum limit
- Selections animate in with a subtle scale+fade

### Psychological Considerations

**Reducing choice paralysis:**
- 8 options is at the upper end of comfortable (Miller's 7 +/- 2). The multi-select nature reduces pressure — users aren't making a permanent exclusive choice.
- Visual grouping helps: row 1 (Budgeting, Protection, Investment, Retirement) = "your money now + later"; row 2 (Estate, Family, Business, Goals) = "your life + plans".
- "Choose one or more" framing reduces commitment anxiety vs "pick your focus area".

**Trust building:**
- No prices, trial countdowns, or urgency language on this page.
- Focus on what the USER wants, not what the app offers.

---

## 4. Design: Dynamic Preview

### Behaviour

When the user selects one or more focus areas, a section appears below the grid with a smooth slide-down animation (no layout shift — use `max-height` transition on a reserved container).

### Layout

```
"Here's what we'll ask you" (heading)

[Personal Details]                    [Financial Details]
- Your date of birth                  - Your properties
- Your annual income                  - Your savings accounts
- Your marital status                 - Your pension details
  (i) Used for protection needs         (i) Used for retirement projections
  and estate planning
```

- Two-column layout on desktop, single column on mobile
- Left column: personal/profile fields
- Right column: financial relationships (accounts, policies, etc.)
- Each item has a small `(i)` info icon that shows the "why" on hover/tap
- Items from the `ModuleDataRequirementsService` "why" text

### Deduplication

When multiple areas are selected, the preview shows the **union** of required fields with duplicates removed. The "why" text combines reasons from all relevant modules.

Example: If user selects Protection + Retirement, `date_of_birth` appears once with combined why: "Used to calculate insurance term lengths and years until you can access your pension."

### Preventing "Scare Factor"

**Risk:** Showing 15+ fields might overwhelm users.
**Mitigation:**
- Group into just 2 categories (Personal + Financial) rather than listing individually
- Show count: "We'll ask you about 6 personal details and 4 financial areas"
- Reassuring footer: "You can skip any question and come back to it later"
- Progressive reveal: collapse the detailed list behind "Show details" toggle, just show the summary counts by default

---

## 5. Field Requirements Per Journey

### Personal/Profile Fields

| Field | Budgeting | Protection | Investment | Retirement | Estate | Family | Business | Goals |
|-------|-----------|------------|------------|------------|--------|--------|----------|-------|
| date_of_birth | | x | x | x | x | | | x |
| annual_employment_income | x | x | x | x | | | | x |
| monthly_expenditure | x | x | | x | | | | x |
| marital_status | | x | | | x | x | | |
| occupation | | x | | | | | x | |
| target_retirement_age | | | x | x | | | | |
| domicile_status | | | | | x | | | |
| employment_status | | | | | | | x | |
| health_status | | x | | | | | | |

### Financial Relationship Fields

| Relationship | Budgeting | Protection | Investment | Retirement | Estate | Family | Business | Goals |
|-------------|-----------|------------|------------|------------|--------|--------|----------|-------|
| savings_accounts | x | | | | | | | |
| investment_accounts | | | x | | x | | | |
| dc_pensions | | | | x | | | | |
| db_pensions | | | | x | | | | |
| state_pension | | | | x | | | | |
| properties | | | | | x | | | |
| mortgages | | x | | | | | | |
| liabilities | | x | | | | | | |
| family_members | | x | | | x | x | | |
| spouse | | | | | x | x | | |
| business_interests | | | | | | | x | |
| protection_policies | | x | | | | | | |
| goals | | | | | | | | x |

### Deduplication Examples

| Selection | Total Personal Fields | Total Financial | Combined Unique |
|-----------|----------------------|-----------------|-----------------|
| Protection only | 5 | 4 | 9 |
| Retirement only | 4 | 3 | 7 |
| Protection + Retirement | 6 (deduplicated) | 6 | 12 |
| All 8 selected | 9 | 13 | 22 |
| Budgeting only | 2 | 1 | 3 |

**Note:** All-8 selection (22 items) is still better than the current full setup (11 dense steps), because the journey is broken into focused mini-sections with context for each question.

---

## 6. User Journey Flows (Per Focus Area)

Each journey follows the same pattern:
1. **Context screen** — Brief explanation of what this journey unlocks
2. **Personal fields** — Profile data needed (with info tooltips)
3. **Financial data** — Module-specific accounts/policies
4. **Completion** — What's now available on their dashboard

### 6.1 Budgeting Journey

**Steps:**
1. Income details (annual_employment_income)
2. Monthly spending (monthly_expenditure)
3. Savings accounts (add/link existing)
4. Completion: "Your budget dashboard is ready — see your savings rate and emergency fund status"

**Dashboard unlocks:** Savings rate card, emergency fund tracker, ISA allowance usage

### 6.2 Protection Journey

**Steps:**
1. Personal details (date_of_birth, marital_status, occupation, health_status)
2. Income (annual_employment_income, monthly_expenditure)
3. Dependants (family_members — children/dependants)
4. Debts (mortgages, liabilities)
5. Existing policies (protection_policies)
6. Completion: "Your protection analysis is ready — check your coverage gaps"

**Dashboard unlocks:** Coverage gap analysis, recommended cover amounts, protection plan

### 6.3 Investment Journey

**Steps:**
1. Personal details (date_of_birth, target_retirement_age)
2. Income (annual_employment_income)
3. Investment accounts (add accounts with holdings)
4. Completion: "Your investment dashboard is ready — see your portfolio analysis"

**Dashboard unlocks:** Portfolio allocation, fee analysis, risk assessment, ISA tracking

### 6.4 Retirement Journey

**Steps:**
1. Personal details (date_of_birth, target_retirement_age)
2. Income & spending (annual_employment_income, monthly_expenditure)
3. Workplace/personal pensions (dc_pensions)
4. Final salary/career average pensions (db_pensions, if applicable)
5. State Pension forecast (state_pension)
6. Completion: "Your retirement projections are ready — see when you can afford to retire"

**Dashboard unlocks:** Retirement projection, Annual Allowance tracker, pension contribution analysis, decumulation strategy

### 6.5 Estate Planning Journey

**Steps:**
1. Personal details (date_of_birth, marital_status, domicile_status)
2. Properties (add properties with values)
3. Investments overview (investment_accounts — if not already added)
4. Spouse details (spouse — if married)
5. Beneficiaries (family_members — if not already added)
6. Completion: "Your estate plan overview is ready — check your Inheritance Tax position"

**Dashboard unlocks:** Inheritance Tax estimate, nil-rate band usage, estate plan, will review reminders

### 6.6 Family Journey

**Steps:**
1. Marital status (marital_status)
2. Spouse details (spouse — invite or add details)
3. Children and dependants (family_members)
4. Completion: "Your family details are saved — this improves protection, estate, and tax calculations"

**Dashboard unlocks:** Enhanced protection recommendations, household coordination (if spouse linked), estate beneficiary mapping

### 6.7 Business Journey

**Steps:**
1. Employment details (occupation, employment_status)
2. Business interests (add businesses with ownership %, valuation)
3. Completion: "Your business interests are recorded — they'll feed into your net worth and estate planning"

**Dashboard unlocks:** Business valuation in net worth, Business Property Relief in estate, exit planning

### 6.8 Goal Tracking Journey

**Steps:**
1. Personal context (date_of_birth, annual_employment_income, monthly_expenditure)
2. Set your first goal (goal type, target amount, target date)
3. Link accounts to goal (optional — savings/investment accounts)
4. Completion: "Your goal is set — track your progress from the dashboard"

**Dashboard unlocks:** Goal progress cards, affordability analysis, milestone tracking

---

## 7. Dashboard Integration

### 7.1 Journey State Cards

For each of the 8 focus areas, the dashboard shows a state-aware card:

**Not Started:**
```
[icon] Protection
Start your protection journey
"Understand your coverage gaps and how to protect your family"
[Start Journey →]
```
- Subtle card: `bg-white border border-light-gray`
- CTA: `text-raspberry-500` link (not a loud button)

**In Progress:**
```
[icon] Protection (2 of 5 steps complete)
[======----] 40%
Continue your protection journey
[Continue →]
```
- Progress bar in `raspberry-500`
- Slightly more prominent: `bg-savannah-100 border border-light-gray`

**Completed:**
```
[checkmark] Protection ✓
Your protection analysis is ready
[View Protection Plan →] [View Coverage Gaps →]
```
- Success state: `bg-spring-50 border border-spring-200`
- Links go directly to the relevant module pages

### 7.2 Card Placement

- Journey cards appear in a dedicated section at the top of the dashboard, below any alert banners
- Grid: 2 columns on desktop, 1 on mobile
- Only show cards for journeys the user selected during onboarding
- Completed journeys collapse to a single line after 7 days (avoid clutter)
- "Not started" journeys show with lower visual priority than "in progress"

### 7.3 Post-Journey Prompts

When a user completes a journey and lands on the dashboard, show a one-time contextual prompt:

| Completed Journey | Dashboard Prompt |
|-------------------|-----------------|
| Budgeting | "Your savings rate is X%. See how your emergency fund is tracking." |
| Protection | "Check your coverage gaps — we've identified areas where you may be underinsured." |
| Investment | "Your portfolio is set up. Review your asset allocation and fee analysis." |
| Retirement | "See your retirement projection — find out when you could afford to retire." |
| Estate Planning | "Your estimated Inheritance Tax position is ready. Review your estate plan." |
| Family | "Your family details enhance protection and estate calculations. Explore household planning." |
| Business | "Your business interests are now included in your net worth. Review your financial position." |
| Goal Tracking | "Your goal is live! Track your progress and see your affordability analysis." |

- Prompts appear as a dismissible banner at the top of the dashboard
- One prompt at a time (if multiple journeys completed simultaneously, queue them)
- Dismissed prompts don't reappear
- CTA button links to the relevant module page

### 7.4 Empty Dashboard

If user skips all journeys or selects nothing:

```
Welcome to Fynla

Your dashboard will come alive as you add your financial information.
Here are two ways to get started:

[Set a Financial Goal]     [Start a Planning Journey]
 Target something specific    Tell us about your finances
 like saving for a deposit    and unlock personalised insights

Or explore on your own — use the navigation to visit any module directly.
```

- Warm, inviting tone — not "your dashboard is empty"
- Two clear CTAs, not 8 overwhelming options
- "Explore on your own" respects user autonomy

---

## 8. Info Tooltips & Learning Prompts

### During Onboarding

Each form field has an info icon `(i)` that:
1. **On first encounter**: Briefly pulses/glows to draw attention, then settles into a static icon
2. **On hover (desktop)**: Shows a tooltip with the "why" text from `ModuleDataRequirementsService`
3. **On tap (mobile)**: Opens a small popover below the field (not a tooltip — touch-friendly)
4. **Dismissible**: Popover closes on tap outside or "Got it" button

### Tooltip Content Pattern

```
[Field Label] ────────────────────
Why we ask: [plain English explanation]
How it's used: [what calculation/insight it powers]
────────────────────────────────────
Skip this if you're not sure — you can add it later.
```

Example for `date_of_birth`:
```
Your Date of Birth
Why we ask: Your age is essential for accurate financial planning.
How it's used: Calculates years to retirement, life expectancy
estimates, and appropriate investment risk levels.
```

### Tone Guidelines (Psychological)

- **Conversational, not clinical**: "Why we ask" not "This field is required for"
- **Benefit-focused**: Lead with what the USER gets, not what the SYSTEM needs
- **Normalising**: "Most people don't know this off the top of their head — you can come back to it"
- **No pressure**: Always include "you can skip this" or "add it later"
- **No jargon**: Plain English, spell out all acronyms (except ISA)

### On Dashboard (First Visit After Journey)

When user first enters the dashboard after completing a journey:
1. A gentle guided highlight appears on the newly unlocked card
2. Brief tooltip: "This is your [module] overview — click to see your full analysis"
3. Disappears after 5 seconds or on interaction
4. Only shows once per completed journey

---

## 9. Behavioural Psychology Design

### Emotional Journey Map

```
Registration → Excitement ("I'm taking control of my finances!")
    ↓
Welcome Page → Warmth ("They know my name, this feels personal")
    ↓
Focus Selection → Empowerment ("I choose what matters to ME")
    ↓
Preview → Confidence ("I can see exactly what's coming, no surprises")
    ↓
Journey Steps → Progress ("I'm building my financial picture step by step")
    ↓
Completion → Achievement ("I did it! And look what I've unlocked")
    ↓
Dashboard → Reward ("My personalised insights are here")
```

### Motivation Design

1. **Intrinsic motivation**: "See your financial picture" not "complete your profile"
2. **Progress visibility**: Progress bar during journey, completion percentage on dashboard
3. **Immediate payoff**: Each completed journey immediately unlocks visible insights
4. **Low commitment entry**: Shortest journey (Budgeting) is just 3 steps — quick win to build momentum
5. **Loss aversion**: Completed journey cards show what's unlocked; not-started cards show what's "waiting for you" (not "missing")

### Financial Anxiety Reduction

- **Normalise incomplete data**: "Most people build their financial picture over time — there's no rush"
- **No judgement language**: Never say "you haven't done X" — say "X is ready when you are"
- **Skip without guilt**: Every step has a skip option with no warning/red text
- **Security reassurance**: Small lock icon + "Your data is encrypted and never shared" on financial data steps

### Colour Psychology Alignment

| Emotion | Stage | Fynla Colour |
|---------|-------|-------------|
| Action/energy | CTAs, start buttons | `raspberry-500` |
| Trust/stability | Text, navigation, headers | `horizon-500` |
| Success/reward | Completion, achievements | `spring-500` |
| Focus/attention | Info tooltips, highlights | `violet-500` |
| Warmth/comfort | Backgrounds, hover states | `savannah-100` |
| Calm/neutral | Page backgrounds | `eggshell-500` |

---

## 10. Risk Analysis & Mitigations

### 10.1 Choice Overload (8 options)

**Risk:** Hick's Law — decision time increases logarithmically with choices.
**Assessment:** Medium risk. 8 is at the upper bound of comfortable (Miller's 7+/-2).
**Mitigation:**
- Multi-select reduces pressure (no exclusive choice to agonise over)
- Visual grouping into 2 rows creates implicit categories
- "Choose one or more" framing is low-commitment
- Default state shows no selections — clean starting point
- If analytics later show low engagement, can reduce to 6 by merging Budgeting into Goal Tracking and Family into Estate Planning

### 10.2 Preview Scare Factor

**Risk:** Showing "what we'll ask" might increase abandonment.
**Assessment:** Medium risk, but user specifically requested this feature.
**Mitigation:**
- Default to summary counts ("6 personal details, 4 financial areas"), not full field list
- "Show details" toggle for users who want to see everything
- Reassuring footer: "You can skip any question and come back later"
- Friendly tone in the preview, not a clinical field list

### 10.3 Mobile Responsiveness

**Risk:** 4x2 grid is too cramped on 375px screens.
**Assessment:** High risk if not handled.
**Mitigation:**
- Responsive grid: 4 cols → 3 cols → 2 cols based on breakpoint
- Cards are touch-target compliant (minimum 48x48px tap area)
- Dynamic preview below grid scrolls naturally on mobile

### 10.4 All-8 Selection

**Risk:** Selecting all 8 areas creates a journey longer than the current 11-step flow.
**Assessment:** Low risk. Deduplicated total is ~22 items, but they're broken into focused mini-sections with context. The current 11 steps are dense multi-field forms. Per-item, the new flow is lighter.
**Mitigation:**
- Show estimated time per journey in the preview ("~2 minutes")
- Allow pausing and resuming — each journey saves progress independently
- Dashboard shows progress per journey, not one giant progress bar

### 10.5 Tooltip Accessibility

**Risk:** Hover tooltips don't work on touch devices (60%+ of users).
**Assessment:** High risk.
**Mitigation:**
- On touch devices: tap `(i)` icon opens a popover (not tooltip)
- Popover is focusable and dismissible with "Got it" button
- All tooltip content also available via `aria-describedby`
- Keyboard users: focus on `(i)` icon shows the content

### 10.6 "Budgeting" Module Gap

**Risk:** Fynla doesn't have a dedicated Budgeting module. The app tracks expenditure and savings but there's no "Budgeting" dashboard page.
**Assessment:** Medium risk — could lead users to a dead end.
**Mitigation:**
- Budgeting journey collects expenditure + savings data
- Dashboard unlocks: savings rate card, emergency fund tracker (these already exist)
- Route to savings module page which has the most relevant content
- Future: dedicated budgeting dashboard can be built later

### 10.7 "Family" Module Gap

**Risk:** Family is part of UserProfile, not a standalone module.
**Assessment:** Low risk — the journey is short (3 steps) and the data feeds into Protection, Estate, and Household features.
**Mitigation:**
- Journey completion routes to the User Profile page (family section)
- Post-journey prompt directs to Protection or Estate which benefit from family data
- "Family" journey is the shortest — sets expectations appropriately

### 10.8 Data Model Complexity

**Risk:** Tracking 8 independent journey states adds complexity.
**Assessment:** Medium risk, but manageable.
**Mitigation:**
- Single JSON column `journey_states` on users table: `{"protection": "completed", "retirement": "in_progress", ...}`
- Backend `JourneyStateService` manages all state transitions
- No new tables needed — just one migration adding the column
- Existing `onboarding_mode` and `onboarding_asset_flags` columns can be deprecated gradually

### 10.9 Existing User Migration

**Risk:** Preview personas and existing users completed old onboarding.
**Assessment:** Low risk.
**Mitigation:**
- Existing users with `onboarding_completed_at` set skip the new welcome page entirely
- Preview personas updated in seeder to have `journey_states` pre-populated
- New onboarding only applies to users registering after deployment

### 10.10 Nag Fatigue

**Risk:** 6 "Start your journey" cards on the dashboard = nagging.
**Assessment:** Medium risk.
**Mitigation:**
- Only show cards for journeys the user SELECTED during onboarding
- Not-started cards have minimal visual weight (white bg, grey text)
- Cards can be dismissed individually ("Hide this")
- After 14 days, un-started cards collapse to a single "Continue setting up" link
- Maximum 3 visible cards at once — "Show more" for additional

---

## 11. Summary of Changes

### Backend Changes
- New `JourneyFieldResolver` service (field deduplication logic)
- New `JourneyStateService` (per-user journey state management)
- New `DashboardPromptService` (post-journey prompt generation)
- Update `OnboardingService` (support multi-journey flow)
- Update `OnboardingController` (new endpoints for journey management)
- Migration: add `journey_states` JSON column to users table
- Update `ModuleDataRequirementsService` (add Budgeting, Family, Business, Goals modules)
- Update `PreviewUserSeeder` (seed journey states for personas)

### Frontend Changes
- Redesign `FocusAreaSelection.vue` (grid + dynamic preview)
- New `JourneyPreview.vue` (dynamic field preview component)
- New `JourneyCard.vue` (dashboard state-aware journey card)
- New `JourneyWelcome.vue` (per-journey context screen)
- New `PostJourneyPrompt.vue` (dismissible dashboard banner)
- New `EmptyDashboard.vue` (empty state with CTAs)
- New `InfoTooltip.vue` (accessible tooltip/popover component)
- Update `Dashboard.vue` (journey cards section, post-journey prompts, empty state)
- Update `OnboardingWizard.vue` (support journey-specific step sequences)
- New Vuex `journeys` store module (journey states, current journey)
- New `journeyService.js` API wrapper
- Update router (journey-specific onboarding routes)

### Data Model
- `users.journey_states` — JSON: `{"budgeting": "not_started", "protection": "completed", ...}`
- `users.journey_selections` — JSON: which areas user chose during onboarding
- `users.dismissed_prompts` — JSON: which post-journey prompts have been dismissed
