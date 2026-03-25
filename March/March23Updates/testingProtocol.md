# Full System Testing Protocol — Production (fynla.org)

**Date:** 23 March 2026
**Environment:** https://fynla.org (production)
**Test account:** chris@fynla.org / Password1! (verification code required)

---

## Phase 1: Authentication

### 1.1 Login
- [ ] Navigate to https://fynla.org/login
- [ ] Enter credentials, submit
- [ ] Enter verification code
- [ ] Verify redirect to dashboard

### 1.2 Session Persistence
- [ ] Refresh the page — stays logged in
- [ ] Navigate to a module page via URL — stays authenticated

---

## Phase 2: Dashboard Verification

### 2.1 Journey Progress
- [ ] Dashboard hero shows journey name, step count, and percentage (e.g. "Starting Out · 5 of 6 steps complete")
- [ ] "Continue Journey" button present and links to onboarding
- [ ] "Next step" card shows with Continue button

### 2.2 Dashboard Cards — All Data Visible
Verify ALL cards with data are showing regardless of journey:
- [ ] Net Worth (Assets / Liabilities breakdown)
- [ ] Protection (coverage, policies, recommended actions)
- [ ] Cash & Savings (total, account list)
- [ ] Investments (portfolio value, accounts)
- [ ] Estate Planning (taxable estate, IHT liability)
- [ ] Retirement (projected income, capital, retirement age)
- [ ] Allowances (ISA, Pension Annual Allowance)
- [ ] Goals & Life Events (projection chart)
- [ ] Life Timeline

### 2.3 Sidebar
- [ ] Sidebar shows correct journey percentage (no 0% flash on load)
- [ ] All navigation sections visible and clickable
- [ ] Sidebar collapses/expands correctly

---

## Phase 3: Onboarding Wizard

### 3.1 Entry
- [ ] Click "Continue Journey" from dashboard
- [ ] Wizard loads with correct step progress (filled circles for completed steps)
- [ ] Current step highlighted

### 3.2 Step 1 — About You (Personal Info)
- [ ] Pre-filled fields: first name, surname, email (read-only)
- [ ] Editable: date of birth, gender
- [ ] Edit date of birth — verify it saves
- [ ] Click Continue — advances to next step
- [ ] Click Back — returns to previous step

### 3.3 Step 2 — Student Loan (if shown for Starting Out journey)
- [ ] Form loads correctly
- [ ] Can skip step
- [ ] Can fill and continue

### 3.4 Step 3 — Income
- [ ] Employment status dropdown works
- [ ] Conditional fields appear (e.g. employer when employed, retirement age when not retired)
- [ ] Income fields accept values
- [ ] Continue saves and advances

### 3.5 Step 4 — Spending (Expenditure)
- [ ] Form loads in edit mode
- [ ] Simple/Detailed mode toggle works
- [ ] Can enter values and save
- [ ] Skip modal appears if all zeros

### 3.6 Step 5 — Assets
- [ ] Tabbed interface: Retirement, Savings, Investments, Properties
- [ ] Can add a pension (DC form opens)
- [ ] Can add a savings account (form opens)
- [ ] Can add an investment account (form opens)
- [ ] Can add a property (PropertyForm opens inline)
- [ ] Existing assets listed and editable

### 3.7 Step 6 — Goals
- [ ] Can add a goal
- [ ] Can skip

### 3.8 Navigation Controls
- [ ] Skip to Dashboard button works (confirmation modal appears)
- [ ] Exit link returns to dashboard
- [ ] Progress bar step circles are clickable
- [ ] Back button works on every step

---

## Phase 4: Module Forms — Create & Edit via Dashboard

### 4.1 Property
- [ ] Navigate to Property page (sidebar)
- [ ] Click existing property → detail view loads
- [ ] Click Edit → form opens with pre-filled data
- [ ] Save without changes — succeeds (no 422)
- [ ] Edit current value, save — value updates
- [ ] Click "Add Property" → empty form opens
- [ ] Fill: type=Main Residence, address, city, postcode, current value
- [ ] Save — property appears in list
- [ ] Edit the new property → data pre-filled correctly
- [ ] Delete the new property — removed from list

### 4.2 Property with Mortgage
- [ ] Edit a property, check "This property has a mortgage"
- [ ] Mortgage step appears
- [ ] Fill: lender, outstanding balance, interest rate, monthly payment
- [ ] Save — mortgage data saved
- [ ] View property detail → Mortgage tab shows data

### 4.3 Savings / Bank Accounts
- [ ] Navigate to Bank Accounts page
- [ ] Click "Add Account"
- [ ] Fill: institution, account type, balance, interest rate
- [ ] Save — account appears
- [ ] Click existing account → detail view
- [ ] Edit account — data pre-filled, save works
- [ ] Delete account

### 4.4 Investments
- [ ] Navigate to Investments page
- [ ] Click "Add Account"
- [ ] Fill: account type (e.g. ISA), provider, current value
- [ ] Save — account appears
- [ ] Click existing account → detail view
- [ ] Edit account — data pre-filled, save works
- [ ] Check Monte Carlo projection chart renders

### 4.5 Retirement — Money Purchase (DC) Pension
- [ ] Navigate to Retirement page
- [ ] Click "Add Pension" → type selector appears
- [ ] Select "Money Purchase"
- [ ] Fill: pension type, scheme name, provider, fund value, contributions
- [ ] Save — pension appears
- [ ] Click existing pension → detail view
- [ ] Edit pension — data pre-filled, save works

### 4.6 Retirement — Defined Benefit (DB) Pension
- [ ] Click "Add Pension" → select "Final Salary"
- [ ] Fill: employer, scheme status, annual income, service years
- [ ] Save — pension appears
- [ ] Edit — pre-filled, save works

### 4.7 Retirement — State Pension
- [ ] Click "Add Pension" → select "State Pension"
- [ ] Fill: forecast weekly amount, qualifying years
- [ ] Save — state pension appears

### 4.8 Protection / Insurance
- [ ] Navigate to Protection page
- [ ] Click "Add New Policy"
- [ ] Select policy type (e.g. Life Insurance)
- [ ] Fill: provider, coverage amount, premium, start/end dates
- [ ] Save — policy appears in list
- [ ] Click existing policy → detail view
- [ ] Edit policy — pre-filled, save works
- [ ] Delete policy

### 4.9 Liabilities
- [ ] Navigate to Liabilities page
- [ ] Click "Add Liability"
- [ ] Fill: type, name, balance, monthly payment, interest rate
- [ ] Save — liability appears
- [ ] Edit — pre-filled, save works
- [ ] Delete

### 4.10 Personal Valuables (Chattels)
- [ ] Navigate to Personal Valuables page
- [ ] Click "Add"
- [ ] Select type (e.g. Vehicle)
- [ ] Fill: name, make/model (if vehicle), current value
- [ ] Save — item appears
- [ ] Edit — pre-filled, save works

### 4.11 Business Interests
- [ ] Navigate to Business page
- [ ] Click "Add Business Interest"
- [ ] Fill steps: Basic Info, Ownership, Valuation, Income/Tax
- [ ] Save — business appears
- [ ] Edit — pre-filled, save works

### 4.12 Goals
- [ ] Navigate to Goals page
- [ ] Click "Add Goal"
- [ ] Fill: name, type, target amount, target date, priority
- [ ] Save — goal appears
- [ ] Edit goal — pre-filled, save works
- [ ] Delete goal

### 4.13 Life Events
- [ ] On Goals page, switch to Life Events tab
- [ ] Click "Add Life Event"
- [ ] Fill: name, event type, amount, expected date, certainty
- [ ] Save — event appears
- [ ] Edit — pre-filled, save works
- [ ] Delete

### 4.14 Family Members
- [ ] Navigate to User Profile → Family tab
- [ ] Click "Add Family Member"
- [ ] Fill: relationship, first name, last name, date of birth, gender
- [ ] Save — member appears
- [ ] Edit — pre-filled, save works

### 4.15 Trusts
- [ ] Navigate to Trusts page
- [ ] Click "Add Trust"
- [ ] Fill: trust name, type, creation date, initial value, current value
- [ ] Save — trust appears
- [ ] Edit — pre-filled, save works

### 4.16 Gifts (Estate)
- [ ] Navigate to Estate Planning page
- [ ] Find gifting section → "Add Gift"
- [ ] Fill: date, recipient, value, type
- [ ] Save — gift appears
- [ ] Edit — pre-filled, save works

### 4.17 Income & Expenditure
- [ ] Navigate to Income page (sidebar → Income)
- [ ] Verify income data displays correctly
- [ ] Navigate to Expenditure page
- [ ] Click Edit → expenditure form opens
- [ ] Modify a value, save — updates correctly

### 4.18 Joint Ownership
- [ ] Edit any asset (property, investment, savings)
- [ ] Change ownership to Joint
- [ ] Verify spouse appears in joint owner dropdown
- [ ] Save — ownership badge shows on card
- [ ] Change to Tenants in Common — percentage field appears
- [ ] Enter percentage, save — share calculation correct

---

## Phase 5: Estate Planning Module

### 5.1 Estate Dashboard
- [ ] Navigate to Estate Planning
- [ ] IHT summary card shows (taxable estate, IHT liability)
- [ ] IHT calculation table renders with correct figures
- [ ] Allowances section shows NRB, RNRB status

### 5.2 Will Builder
- [ ] Navigate to Will (sidebar)
- [ ] Wizard loads with steps
- [ ] Step through: Personal → Executors → Guardians → Gifts → Residuary → Funeral → Digital → Signing → Review
- [ ] Each step saves progress
- [ ] Review step shows summary

### 5.3 Power of Attorney
- [ ] Navigate to Power of Attorney (sidebar)
- [ ] View existing LPA or create new
- [ ] If creating: select type (Property & Financial / Health & Welfare)
- [ ] Step through wizard: Donor → Attorneys → Replacement → Notification → Certificate Provider → Preferences → Review

---

## Phase 6: Planning & Analysis

### 6.1 Risk Profile
- [ ] Navigate to Risk Profile (sidebar)
- [ ] Auto-calculated risk level displays
- [ ] Factor breakdown shows 9 factors
- [ ] Drill-down links work (levels, individual factors)

### 6.2 Net Worth
- [ ] Navigate to Net Worth (sidebar)
- [ ] Wealth summary page loads
- [ ] Assets breakdown by category
- [ ] Liabilities listed
- [ ] Total net worth calculated correctly

### 6.3 Holistic Plan
- [ ] Navigate to Holistic Plan
- [ ] Page loads with plan sections

### 6.4 What If Scenarios
- [ ] Navigate to What If Scenarios
- [ ] Page loads
- [ ] "Death of Spouse" scenario accessible if applicable

### 6.5 Actions
- [ ] Navigate to Actions
- [ ] Recommended actions listed by module
- [ ] Click an action → detail view

---

## Phase 7: Fyn AI Assistant

### 7.1 Chat Interface
- [ ] Click Fyn panel (bottom-right)
- [ ] Panel opens with greeting message
- [ ] Suggested prompts visible (e.g. "How much am I spending?", "What is my net worth?")
- [ ] Input field active, Enter to send works

### 7.2 Conversations
- [ ] Type a question (e.g. "What is my net worth?") and send
- [ ] Loading indicator (bouncing dots) appears
- [ ] Response streams in
- [ ] Response includes relevant financial data
- [ ] Click "New conversation" (+) — clears chat
- [ ] Click "Conversation history" — drawer shows past conversations
- [ ] Click a past conversation — loads messages

### 7.3 Navigation Actions
- [ ] Ask Fyn "Take me to my investments"
- [ ] Fyn responds with a navigation card
- [ ] Click the card — navigates to the correct page

### 7.4 Advisory Responses
- [ ] Ask "How much life insurance do I need?"
- [ ] Fyn responds with personalised advice based on user data
- [ ] Ask "What is my Inheritance Tax liability?"
- [ ] Fyn responds with IHT figures from estate data
- [ ] Ask "Am I saving enough for retirement?"
- [ ] Fyn responds with pension/retirement analysis

### 7.5 Form Fill (AI-Assisted Data Entry)
- [ ] Ask Fyn to add data (e.g. "Add a savings account at Halifax with £5,000")
- [ ] Verify entity created card appears (green)
- [ ] Navigate to the module — new item exists with correct data
- [ ] Delete the test item

---

## Phase 8: Cross-Module Verification

### 8.1 Data Consistency
- [ ] Add a property → check Net Worth updates
- [ ] Add a pension → check Retirement page updates
- [ ] Add a savings account → check Cash & Savings updates
- [ ] Add a liability → check Net Worth liabilities update
- [ ] Add a protection policy → check Protection page updates

### 8.2 Journey Progress Updates
- [ ] After adding data via forms, return to dashboard
- [ ] Journey progress percentage should reflect new completeness
- [ ] Sidebar percentage should match dashboard hero

### 8.3 Joint Data Visibility
- [ ] Add a joint property → verify it shows correct ownership split
- [ ] Joint asset appears on both owner's view (if spouse account exists)

---

## Phase 9: Navigation & UI

### 9.1 All Sidebar Links
Navigate to each and verify page loads without errors:
- [ ] Dashboard
- [ ] Net Worth
- [ ] Bank Accounts
- [ ] Income
- [ ] Expenditure
- [ ] Investments
- [ ] Retirement
- [ ] Property
- [ ] Liabilities
- [ ] Personal Valuables
- [ ] Risk Profile
- [ ] Business
- [ ] Protection
- [ ] Will
- [ ] Expression of Wishes
- [ ] Trusts
- [ ] Estate Planning
- [ ] Power of Attorney
- [ ] Holistic Plan
- [ ] Plans
- [ ] Journeys
- [ ] What If Scenarios
- [ ] Goals
- [ ] Life Events
- [ ] Actions
- [ ] User Profile
- [ ] Settings
- [ ] Help

### 9.2 Settings Pages
- [ ] Settings → main page loads
- [ ] Security settings page loads
- [ ] Privacy settings page loads
- [ ] Assumptions settings page loads

### 9.3 Dashboard Card Navigation
- [ ] Click each dashboard card → navigates to correct module page
- [ ] Back navigation works from each module

---

## Phase 10: Edge Cases & Error Handling

### 10.1 Form Validation
- [ ] Submit empty required fields → validation errors shown
- [ ] Enter invalid postcode → error shown
- [ ] Enter negative currency value → rejected or error
- [ ] Enter future date for purchase_date → accepted (or validated)
- [ ] Enter past date for target_date on goal → validation error

### 10.2 Concurrent Data
- [ ] Edit a property in one tab, view in another — no data corruption
- [ ] Rapid navigation between pages — no console errors or crashes

### 10.3 Session Handling
- [ ] Leave page idle for extended time → re-authentication if session expires
- [ ] Logout → redirects to login, cannot access authenticated pages

---

## Test Completion Checklist

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Authentication | |
| 2 | Dashboard | |
| 3 | Onboarding Wizard | |
| 4 | Module Forms (Create & Edit) | |
| 5 | Estate Planning | |
| 6 | Planning & Analysis | |
| 7 | Fyn AI Assistant | |
| 8 | Cross-Module Verification | |
| 9 | Navigation & UI | |
| 10 | Edge Cases | |

**RULE: Every checkbox marked [x] must have a corresponding Playwright interaction (click, fill, submit, verify). Reading a snapshot without interaction is NOT testing.**
