# Fynla Comprehensive Testing Checklist — v0.8.3

**Date:** 5 March 2026
**Environment:** https://fynla.org (production) or http://localhost:8000 (local)
**Login:** chris@fynla.org / Password1! (verification code required)

---

## Pre-Testing Setup

- [ ] `php artisan db:seed` runs cleanly
- [ ] `./deploy/fynla-org/build.sh` completes without errors
- [ ] `./vendor/bin/pest` — all tests pass
- [ ] Dev server running (`./dev.sh`) if testing locally
- [ ] Test in incognito/private browsing window

---

## 1. Authentication & Security

### Registration
- [ ] Register page loads with hi-res logo and raspberry CTAs
- [ ] Form validates required fields (name, email, password)
- [ ] Password strength requirements enforced
- [ ] Successful registration sends verification code email
- [ ] Verification code modal appears after registration
- [ ] Valid code completes registration and redirects to onboarding
- [ ] Invalid code shows error, allows retry
- [ ] Resend code button works
- [ ] Duplicate email shows appropriate error

### Login
- [ ] Login page loads with correct branding (raspberry buttons, eggshell background)
- [ ] Valid credentials proceed to verification code step
- [ ] Invalid credentials show error message
- [ ] Account lockout after repeated failed attempts
- [ ] Verification code entry works correctly
- [ ] Successful login redirects to dashboard
- [ ] "Forgot password" link works

### Multi-Factor Authentication (MFA)
- [ ] MFA setup accessible from Security Settings
- [ ] QR code displayed for authenticator app scanning
- [ ] TOTP code verification works during setup
- [ ] Recovery codes generated and displayed
- [ ] MFA required on subsequent logins when enabled
- [ ] Recovery code login works as fallback
- [ ] MFA can be disabled from settings

### Password Reset
- [ ] "Forgot password" initiates reset flow
- [ ] Email with verification code sent
- [ ] Code verification step works
- [ ] MFA verification required if MFA enabled
- [ ] New password set successfully
- [ ] Can login with new password

### Session Management
- [ ] Session persists across page refreshes
- [ ] Logout clears session and redirects to landing page
- [ ] Session timeout after inactivity
- [ ] Multiple concurrent sessions tracked in Security Settings

---

## 2. Onboarding Wizard

- [ ] New user redirected to onboarding after registration
- [ ] Personal information step collects name, DOB, marital status
- [ ] Spouse details step appears when married/civil partnership selected
- [ ] Focus area selection step works (multiple selections allowed)
- [ ] Planning assumptions step shows default values
- [ ] Each step validates before advancing
- [ ] Back button returns to previous step
- [ ] "Skip to Dashboard" modal appears and works
- [ ] Progress indicator shows current step
- [ ] Completing onboarding redirects to dashboard

---

## 3. Dashboard

### Layout & Design
- [ ] Page background is warm eggshell (#F7F6F4), not cool grey
- [ ] Navigation bar uses dark logo
- [ ] Footer uses light logo
- [ ] All cards use correct palette colours (no blue, green, red, amber, orange)
- [ ] Raspberry CTAs on all action buttons
- [ ] Violet focus rings on interactive elements

### Dashboard Cards
- [ ] Net worth overview card shows total with breakdown
- [ ] Investments overview card shows portfolio value
- [ ] Goals overview card shows progress indicators
- [ ] Actions overview card shows top recommendations
- [ ] Affordability overview card shows budget adequacy
- [ ] Tax optimisation card shows allowance usage
- [ ] Areas to complete card shows missing data prompts
- [ ] Areas to consider card shows suggested focus areas
- [ ] Alerts panel shows relevant notifications
- [ ] Financial Health Overview shows descriptive labels (Good/Fair/Needs attention), NO numeric scores (/100)
- [ ] Widget order is customisable (drag and drop or settings)

### Data Accuracy
- [ ] Net worth figure matches sum of all assets minus liabilities
- [ ] Goal progress percentages are accurate
- [ ] Recommendation count matches aggregated module recommendations
- [ ] Tax allowance figures match current tax year (2025/26)

---

## 4. Navigation

### Side Menu
- [ ] Side menu opens and closes correctly
- [ ] Menu sections organised as: Current / Planning / Family
- [ ] All menu items navigate to correct pages
- [ ] Active page highlighted in menu
- [ ] Mobile toggle works on small screens
- [ ] Menu icons display correctly

### Navbar
- [ ] Logo links to dashboard
- [ ] User menu shows profile options
- [ ] Logout option works
- [ ] Trial countdown banner shows if applicable
- [ ] Preview banner shows for preview users

---

## 5. Net Worth Module

### Wealth Summary
- [ ] Total net worth displayed correctly
- [ ] Breakdown by asset class (property, investments, savings, pensions, business, chattels)
- [ ] Liabilities subtracted correctly
- [ ] Joint assets show correct ownership split
- [ ] Asset allocation chart renders

### Properties
- [ ] Property list displays all properties
- [ ] Add property form works (main residence, secondary, buy-to-let)
- [ ] Edit property details
- [ ] Delete property with confirmation
- [ ] Property valuation and growth projection
- [ ] Joint ownership fields work correctly
- [ ] SDLT calculator works
- [ ] CGT calculator works for disposal scenarios
- [ ] Rental income tax calculation works for buy-to-let

### Mortgages
- [ ] Mortgage list under each property
- [ ] Add mortgage (repayment, interest-only, mixed)
- [ ] Edit mortgage details
- [ ] Delete mortgage
- [ ] Amortisation schedule generates correctly
- [ ] Payment calculator works

### Investment Accounts
- [ ] Investment list displays all accounts
- [ ] Add investment account (ISA, SIPP, GIA, CTF, employee scheme)
- [ ] Edit account details
- [ ] Delete account with confirmation
- [ ] Holdings management (add/edit/delete securities)
- [ ] Account type badges display correctly (ISA blue, SIPP purple, etc.)

### Investment Analysis
- [ ] Portfolio analysis tab shows asset allocation
- [ ] Diversification analysis displays metrics
- [ ] Rebalancing calculator shows drift from target
- [ ] Fee analysis shows OCF impact
- [ ] Tax optimisation shows bed & ISA, CGT harvesting opportunities
- [ ] Asset location optimiser recommends account placement
- [ ] Efficient frontier calculation runs
- [ ] Monte Carlo simulation generates probability ranges
- [ ] Performance attribution shows alpha/beta
- [ ] Benchmark comparison works
- [ ] Investment projection chart renders with life event SVG icons
- [ ] Chart expense annotations use raspberry colour (not red)

### Pensions
- [ ] Pension list displays all pensions (DC, DB, State)
- [ ] Add DC pension with contribution details
- [ ] Add DB pension with accrual details
- [ ] Add/edit state pension details
- [ ] DC pension holdings management
- [ ] Pension pot projection chart renders with life event icons
- [ ] Annual allowance tracker shows usage (GBP 60,000 limit)
- [ ] Retirement income projection works
- [ ] Required capital calculation works
- [ ] Drawdown simulator works
- [ ] Contribution optimiser generates recommendations

### Savings
- [ ] Savings account list displays all accounts
- [ ] Add savings account (cash ISA, instant access, notice, fixed)
- [ ] Edit account details
- [ ] Delete account
- [ ] Emergency fund gauge shows adequacy (3-6 months)
- [ ] ISA allowance tracker shows remaining (GBP 20,000)
- [ ] Interest rate comparison chart works
- [ ] Savings goals display with progress

### Cash Overview
- [ ] Cash accounts display with balances
- [ ] Balance trend chart renders
- [ ] Spending breakdown donut chart works
- [ ] Cash insights panel shows analytics

### Business Interests
- [ ] Business interest list displays
- [ ] Add business interest (unquoted shares, partnership)
- [ ] Edit details
- [ ] Delete with confirmation
- [ ] Tax deadline tracking works
- [ ] Exit calculation works
- [ ] CGT calculation works
- [ ] Joint ownership fields work

### Chattels (Personal Possessions)
- [ ] Chattels list displays
- [ ] Add chattel (art, jewellery, antiques, vehicles)
- [ ] Edit details
- [ ] Delete with confirmation
- [ ] CGT calculation works (chattel exemption under GBP 6,000)
- [ ] Joint ownership fields work

### Liabilities
- [ ] Liabilities list displays all debts
- [ ] Add liability
- [ ] Edit details
- [ ] Delete with confirmation
- [ ] Liabilities subtracted from net worth correctly

### Joint Account History
- [ ] Joint account activity log displays
- [ ] Shows correct ownership splits
- [ ] Historical changes tracked

---

## 6. Protection Module

### Policies
- [ ] Protection dashboard loads without ModuleLifeEvents card
- [ ] Protection dashboard loads without Recommended Strategies card
- [ ] Add life insurance policy
- [ ] Add critical illness policy
- [ ] Add income protection policy
- [ ] Add disability policy
- [ ] Add sickness/illness policy
- [ ] Edit policy details
- [ ] Delete policy with confirmation
- [ ] Policy detail view shows all information
- [ ] Policy form modal emits `save` (not `submit`)

### Analysis
- [ ] Coverage gap analysis identifies shortfalls
- [ ] Coverage adequacy gauge displays correctly
- [ ] Coverage timeline chart renders
- [ ] Premium breakdown chart renders
- [ ] Recommendations generated based on gaps
- [ ] Scenario builder works (increase coverage, replace policies)

### Colours
- [ ] All success indicators use spring green (not green)
- [ ] All warning/info indicators use violet (not blue/amber)
- [ ] All error/danger indicators use raspberry (not red)
- [ ] No blue, green, red, purple, teal, amber, or orange tokens visible

---

## 7. Estate Planning Module

### Dashboard
- [ ] Estate dashboard loads without ModuleLifeEvents card
- [ ] IHT liability summary displays correctly
- [ ] IHT summary card values align with calculation table values
- [ ] Net worth breakdown for estate purposes
- [ ] Asset/liability lists display correctly
- [ ] Spring/raspberry colours only (no old palette)

### IHT Calculations
- [ ] IHT calculation table shows correct figures
- [ ] NRB (GBP 325,000) applied correctly
- [ ] RNRB (GBP 175,000) applied for main residence left to direct descendants
- [ ] RNRB taper applied above GBP 2M estate
- [ ] Charitable rate (36%) applied when 10%+ left to charity
- [ ] Spouse exemption applied correctly
- [ ] Projected IHT values from service not overwritten by controller
- [ ] Joint assets included with correct ownership shares

### Will Management
- [ ] Create/edit will
- [ ] Add bequests (specific gifts, residual estate, charity)
- [ ] Will analysis summary displays
- [ ] Intestacy calculator shows distribution under intestacy rules

### Gifting Strategy
- [ ] Gift list displays
- [ ] Add gift (annual exemption, PET, CLT)
- [ ] Gifting timeline chart renders
- [ ] Dual gifting timeline shows both spouses
- [ ] Gifting strategy optimiser generates recommendations
- [ ] Annual exemption (GBP 3,000) tracked correctly
- [ ] PET taper rules applied over 7-year period

### Trusts
- [ ] Trusts dashboard loads
- [ ] Add trust (discretionary, bare, interest-in-possession)
- [ ] Edit trust details
- [ ] Delete trust
- [ ] Trust detail view shows all information
- [ ] Periodic charge calculation works (10-yearly)
- [ ] Exit charge calculation works
- [ ] Upcoming tax returns displayed
- [ ] Trust strategy recommendations generated

### Life Policies (Estate)
- [ ] Life policy list displays
- [ ] Add life policy for IHT cover
- [ ] Life cover calculator determines amount needed
- [ ] Policy strategy recommendations generated

---

## 8. Goals & Life Events Module

### Goals
- [ ] Goals dashboard loads
- [ ] Add financial goal (education, wedding, home, retirement, custom)
- [ ] Edit goal details
- [ ] Delete goal with confirmation
- [ ] Goal progress bar shows accurate percentage
- [ ] Goal countdown shows time remaining
- [ ] Goal contribution streak tracking works
- [ ] Goal milestone tracker displays
- [ ] Add manual contribution
- [ ] Link goal to savings account
- [ ] Link goal to investment account
- [ ] Auto-contribution tracking works when linked account balance increases
- [ ] Goal affordability analysis generates
- [ ] Goal projection chart renders
- [ ] Goals by module view works

### Life Events
- [ ] Life event list displays
- [ ] Add life event (marriage, children, career change, property purchase)
- [ ] Edit life event
- [ ] Delete life event
- [ ] Life event detail view shows information
- [ ] Allocate goals to life events
- [ ] Life event icons display on projection charts (SVG overlays)
- [ ] Life event cash flow impact calculated

---

## 9. Plans System

### Plans Dashboard
- [ ] Plans dashboard shows all 5 plan types with correct palette cards
- [ ] Card colours use violet, spring, raspberry, horizon (no banned tokens)
- [ ] Each plan card shows status and last generated date

### Investment Plan
- [ ] Plan generates with executive summary
- [ ] Current situation section shows portfolio analysis
- [ ] Grouped actions listed by account
- [ ] Toggle actions on/off
- [ ] What-if scenario updates reactively when actions toggled
- [ ] Projection chart updates with enabled actions
- [ ] Dynamic conclusion reflects enabled actions
- [ ] PDF export works with correct charts and headers

### Protection Plan
- [ ] Plan generates with executive summary
- [ ] Current situation shows coverage analysis
- [ ] Actions listed with coverage gap recommendations
- [ ] Toggle actions on/off
- [ ] What-if scenario updates reactively
- [ ] PDF export works

### Retirement Plan
- [ ] Plan generates with executive summary
- [ ] Current situation shows pension analysis
- [ ] Per-pension grouped actions displayed
- [ ] Toggle actions on/off
- [ ] What-if scenario updates reactively
- [ ] PDF export works

### Estate Plan
- [ ] Plan generates with executive summary
- [ ] Current situation shows IHT analysis
- [ ] IHT mitigation actions listed
- [ ] Toggle actions on/off
- [ ] What-if scenario updates reactively
- [ ] PDF export works

### Goal Plan
- [ ] Plan generates with executive summary for selected goal
- [ ] Current situation shows goal progress
- [ ] Tax-aware funding source dropdowns work
- [ ] Toggle actions on/off
- [ ] What-if scenario updates reactively
- [ ] PDF export works

### Holistic Plan
- [ ] Holistic plan aggregates all module plans
- [ ] Executive summary shows personalised narrative with user name
- [ ] Plans included badges display correctly (violet chips)
- [ ] Priority area recommendations show with allocation bars
- [ ] Module sections display for each available module
- [ ] Conclusion summarises all recommendations
- [ ] Priority badge colours use palette tokens (not banned colours)

### Shared Plan Components
- [ ] PlanSectionHeader colour prop works (violet, spring, raspberry, horizon)
- [ ] PlanActionCard shows no dev placeholder text
- [ ] PlanActionCard toggle switches work
- [ ] PlanActionsList forwards `update-funding-source` event correctly
- [ ] PlanMissingDataPrompt uses violet tokens (not blue)
- [ ] PlanErrorState uses raspberry tokens (not red)
- [ ] PlanWhatIfMetricRow uses spring/raspberry for positive/negative (not green/red)
- [ ] PlanConclusion badges use palette tokens
- [ ] PlanGoalSection badges use spring/violet/raspberry (not green/blue/red)
- [ ] PlanDashboardCard colour map uses palette tokens

---

## 10. Risk Profiling

- [ ] Risk profile page loads
- [ ] Risk questionnaire works (answer all questions)
- [ ] Risk score calculated and displayed
- [ ] Risk factor breakdown shows
- [ ] Risk levels explained page loads with educational content
- [ ] Risk factor detail page shows specific factor analysis
- [ ] Risk badge displays correct level
- [ ] Risk level selector works for preference override

---

## 11. Actions & Recommendations

- [ ] Actions dashboard loads (no 404 errors)
- [ ] Actions dashboard uses proper AppLayout
- [ ] Recommendations loaded from all modules (protection, savings, investment, retirement, estate)
- [ ] Recommendations sorted by priority (highest first)
- [ ] Filter by module works
- [ ] Filter by priority works
- [ ] Filter by timeline works
- [ ] Mark action as done
- [ ] Mark action as in-progress
- [ ] Dismiss action
- [ ] Add notes to action
- [ ] No `/api/api/` double prefix in API calls

---

## 12. Tax Module

- [ ] UK Taxes dashboard loads (admin only)
- [ ] Tax status panel shows current allowance usage
- [ ] Tax calculations tab shows detailed breakdown
- [ ] Income tax calculated correctly
- [ ] National Insurance calculated correctly
- [ ] Dividend tax calculated correctly
- [ ] All values sourced from TaxConfigService (no hardcoded figures)

---

## 13. User Profile & Settings

### Personal Information
- [ ] View personal details
- [ ] Edit name, DOB, marital status
- [ ] Edit nationality, UK residency status
- [ ] Domicile information section works
- [ ] Profile completeness indicator updates

### Income & Occupation
- [ ] View income details
- [ ] Edit employment status, gross income
- [ ] Occupation autocomplete works
- [ ] Income changes trigger risk recalculation

### Expenditure
- [ ] View expenditure overview
- [ ] Add/edit expenditure categories
- [ ] Expandable grid rows work
- [ ] Category cards display correctly
- [ ] Expenditure changes affect emergency fund adequacy

### Family Members
- [ ] Family member list displays
- [ ] Add family member (child, dependent)
- [ ] Edit family member details
- [ ] Delete family member
- [ ] Family changes trigger risk recalculation

### Settings Pages
- [ ] Security settings page loads
- [ ] Security settings uses palette tokens (no hardcoded hex values)
- [ ] MFA section works
- [ ] Password change works
- [ ] Active sessions displayed
- [ ] Privacy settings page loads
- [ ] Consent toggles work
- [ ] Data export request works
- [ ] Data erasure flow works (with verification)
- [ ] Assumptions settings page loads
- [ ] Inflation rate editable
- [ ] Investment return assumptions editable
- [ ] Life expectancy editable
- [ ] Assumption changes affect all projections

### Subscription Management
- [ ] Current plan displayed
- [ ] Billing history accessible
- [ ] Plan upgrade/downgrade works
- [ ] Cancellation flow works

---

## 14. Spouse & Joint Features

### Spouse Data Sharing
- [ ] Request spouse permission
- [ ] Accept permission request
- [ ] Reject permission request
- [ ] Revoke permission
- [ ] Spouse financial commitments visible when linked
- [ ] Letter to spouse accessible

### Joint Assets
- [ ] Joint property shows for both spouses
- [ ] Joint savings account shows for both spouses
- [ ] Joint investment account shows for both spouses
- [ ] Ownership percentages calculated correctly
- [ ] Joint assets appear in both users' net worth
- [ ] Joint assets appear in both users' estate calculations
- [ ] No duplicate records created for joint ownership

---

## 15. Document Upload & AI Extraction

- [ ] Document upload modal opens
- [ ] Drag-and-drop upload works
- [ ] File selection upload works
- [ ] Document type auto-detected
- [ ] AI extraction processes successfully
- [ ] Extracted fields displayed for review
- [ ] Confirm extraction populates correct module data
- [ ] Reprocess extraction works
- [ ] Delete document works
- [ ] Supported formats: images, PDFs, Excel

---

## 16. AI Chat Assistant

- [ ] Chat button visible on authenticated pages
- [ ] Chat panel opens
- [ ] Send message and receive response
- [ ] Conversation history persists
- [ ] New conversation creation works
- [ ] Delete conversation works
- [ ] Responses reflect user's financial context
- [ ] Preview mode shows simulated responses

---

## 17. Payments & Subscriptions

### Checkout
- [ ] Pricing page displays three tiers with correct prices
- [ ] Plan selection works
- [ ] Checkout page loads Revolut embedded checkout
- [ ] Card payment processes successfully
- [ ] Payment confirmation redirects to dashboard
- [ ] Subscription activated after payment

### Trial
- [ ] Trial countdown banner shows days remaining
- [ ] Trial expiry triggers appropriate behaviour
- [ ] Trial status API returns correct data

### Billing
- [ ] Billing history shows past payments
- [ ] Subscription cancellation flow works
- [ ] Data retention overlay shown during cancellation
- [ ] Data purge option works when cancelling

---

## 18. Admin Panel

### User Management
- [ ] Admin dashboard loads with stats
- [ ] User list displays all users
- [ ] Create new user
- [ ] Edit user details
- [ ] Delete user
- [ ] Assign roles to users

### Action Definitions
- [ ] Protection action definitions CRUD works
- [ ] Investment action definitions CRUD works
- [ ] Retirement action definitions CRUD works
- [ ] Enable/disable action toggle works

### Tax Configuration
- [ ] View tax configurations for all years
- [ ] Create new tax configuration
- [ ] Edit tax values
- [ ] Activate tax configuration for current year
- [ ] Duplicate configuration to new year

### Database
- [ ] Database backup works
- [ ] Database restore works (test in non-production only)

---

## 19. Preview Mode (All 7 Personas)

### Persona Loading
- [ ] Landing page persona selector displays all 7 personas
- [ ] **Young Family** (James & Emily Carter) loads correctly
- [ ] **Peak Earners** (David & Sarah Mitchell) loads correctly
- [ ] **Widow** (Margaret Thompson) loads correctly
- [ ] **Entrepreneur** (Alex Chen) loads correctly
- [ ] **Young Saver** (John Morgan) loads correctly
- [ ] **Retired Couple** (Robert & Patricia Williams) loads correctly
- [ ] **Student** (Janice Taylor) loads correctly

### Preview Behaviour
- [ ] Preview banner visible at top of page
- [ ] `v-preview-disabled` elements show disabled state with tooltip
- [ ] Write operations return fake success (no actual data persisted)
- [ ] Calculations and analysis run normally (read-only endpoints work)
- [ ] Switch between personas works
- [ ] Exit preview mode works
- [ ] No 403 errors on any preview page

### Per-Persona Verification
For each persona, verify:
- [ ] Dashboard loads with populated data
- [ ] Net worth figures are reasonable for persona profile
- [ ] Protection module shows relevant policies
- [ ] Savings module shows accounts and emergency fund status
- [ ] Investment module shows appropriate portfolio
- [ ] Retirement module shows relevant pensions
- [ ] Estate module shows IHT calculations
- [ ] Goals module shows persona-appropriate goals
- [ ] Plans generate without errors for each plan type

---

## 20. Design System Compliance

### Colours
- [ ] Page background is eggshell (#F7F6F4) throughout
- [ ] Primary CTAs use raspberry-500 (#E83E6D)
- [ ] Text uses horizon-500 (#1F2A44)
- [ ] Success states use spring-500 (#20B486)
- [ ] Warning/focus states use violet-500 (#5854E6)
- [ ] Muted text uses neutral-500 (#717171)
- [ ] Borders use light-gray (#EEEEEE)
- [ ] No amber, orange, blue, green, red, purple, or teal in Plans components
- [ ] No hardcoded hex values in component style blocks
- [ ] Chart colours sourced from `designSystem.js` constants

### Typography
- [ ] Primary font: Segoe UI (system font)
- [ ] Fallback font: Inter (Google Fonts)
- [ ] Display/H1 weight: 900 (Black)
- [ ] H2-H5 weight: 700 (Bold)

### Components
- [ ] Form inputs have violet focus rings
- [ ] Success messages use spring green
- [ ] Error messages use raspberry
- [ ] Buttons follow palette (raspberry primary, horizon secondary)
- [ ] Cards use consistent border and shadow patterns
- [ ] Badges use correct palette (account type badges unchanged)
- [ ] Spinners use `border-horizon-200 border-t-raspberry-500` pattern

### Logos
- [ ] Navigation bar shows dark logo (`LogoHiResFynlaDark.png`)
- [ ] Footer shows light logo (`LogoHiResFynlaLight.png`)
- [ ] Login/Register pages show hi-res logo
- [ ] Favicon loads correctly

---

## 21. Content & Copy Rules

### No Scores (Rule 12)
- [ ] Financial Health Overview shows descriptive labels only (Good/Fair/Needs attention)
- [ ] No numeric ratings (/100, /10) visible anywhere in UI
- [ ] No score badges, score metric cards, or score-formatted values
- [ ] No score-based narrative text

### No Acronyms (Rule 10)
- [ ] "Annual Allowance" not "AA"
- [ ] "Stocks & Shares" not "S&S"
- [ ] "Defined Benefit" not "DB" in user-facing text
- [ ] "Defined Contribution" not "DC" in user-facing text
- [ ] "Money Purchase Annual Allowance" not "MPAA" in user-facing text
- [ ] ISA remains abbreviated (exception)

### British Spelling (User-Facing)
- [ ] "Optimisation" not "Optimization"
- [ ] "Customise" not "Customize"
- [ ] "Analyse" not "Analyze" in UI labels

---

## 22. Responsive & Cross-Browser

### Mobile (< 768px)
- [ ] Side menu collapses to mobile toggle
- [ ] Dashboard cards stack vertically
- [ ] Forms are usable on small screens
- [ ] Charts resize appropriately
- [ ] Modals are scrollable and closeable
- [ ] Navigation is accessible

### Tablet (768px - 1024px)
- [ ] Layout adapts to medium screens
- [ ] Cards use appropriate grid layout
- [ ] Charts render at correct size

### Desktop (> 1024px)
- [ ] Full layout with side menu
- [ ] Cards use multi-column grid
- [ ] Charts render at full size

---

## 23. Error Handling & Edge Cases

- [ ] Empty state displays when no data exists for a module
- [ ] API errors show user-friendly messages (not raw error objects)
- [ ] Network timeout shows retry option
- [ ] 401 redirects to login
- [ ] 403 shows appropriate access denied message
- [ ] 404 shows not found page
- [ ] 429 (rate limit) handled gracefully
- [ ] Form validation errors display inline
- [ ] Modal stays open on save error, closes on success
- [ ] Currency values display correctly (GBP with commas)
- [ ] Percentage values display correctly
- [ ] Zero values handled (no division by zero)
- [ ] Negative values displayed appropriately
- [ ] Very large values formatted correctly (GBP 999,999,999.99 max)
- [ ] Date formats correct (DD/MM/YYYY for display, YYYY-MM-DD for inputs)

---

## 24. Performance

- [ ] Dashboard loads within 3 seconds
- [ ] Module pages load within 3 seconds
- [ ] Plan generation completes within 10 seconds
- [ ] Monte Carlo simulations complete within 15 seconds
- [ ] No visible layout shift during loading
- [ ] Loading spinners display during data fetches
- [ ] No console errors in browser developer tools
- [ ] No 404s for assets or API calls

---

## 25. Public Pages

- [ ] Landing page loads without authentication
- [ ] Persona selector works on landing page
- [ ] Pricing page displays correct tiers and prices
- [ ] Calculators page loads with working calculators
- [ ] Learning centre loads with educational content
- [ ] About page loads
- [ ] Security page loads
- [ ] Privacy policy page loads
- [ ] Terms of service page loads
- [ ] Sitemap page loads
- [ ] All public pages use PublicLayout (not AppLayout)

---

## 26. GDPR Compliance

- [ ] Privacy settings show consent toggles
- [ ] Consent changes recorded with history
- [ ] Data export generates downloadable file (CSV/JSON)
- [ ] Data erasure initiates with email verification
- [ ] Data erasure requires MFA verification if enabled
- [ ] Data erasure confirmation step works
- [ ] Data erasure can be cancelled
- [ ] Audit logs track all consent changes

---

## 27. Bug Reporting

- [ ] Bug report modal accessible from navigation
- [ ] Bug report form submits successfully
- [ ] User context included in report

---

## Post-Testing Sign-Off

| Area | Tester | Status | Notes |
|------|--------|--------|-------|
| Authentication & Security | | | |
| Onboarding | | | |
| Dashboard | | | |
| Net Worth (Properties) | | | |
| Net Worth (Investments) | | | |
| Net Worth (Pensions) | | | |
| Net Worth (Savings) | | | |
| Net Worth (Business/Chattels) | | | |
| Protection Module | | | |
| Estate Planning | | | |
| Goals & Life Events | | | |
| Plans System | | | |
| Risk Profiling | | | |
| Actions & Recommendations | | | |
| User Profile & Settings | | | |
| Spouse & Joint Features | | | |
| Document Upload | | | |
| AI Chat | | | |
| Payments & Subscriptions | | | |
| Admin Panel | | | |
| Preview Mode (All Personas) | | | |
| Design System Compliance | | | |
| Content & Copy Rules | | | |
| Responsive Design | | | |
| Error Handling | | | |
| Performance | | | |
| Public Pages | | | |
| GDPR Compliance | | | |

**Total Checks:** 400+
