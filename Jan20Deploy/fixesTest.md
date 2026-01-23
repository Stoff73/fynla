# v0.6.2 Fixes Summary

## BUG-001: Investment Dashboard Strategy Card Not Showing
Strategy card wasn't displaying for users without holdings or risk profile.
Added recommendations that trigger without holdings data to ensure card always appears.

## UI-001: Remove Strategy Count Badge from Strategy Card
Removed redundant "4 strategies" badge from Investment dashboard.
Cleaner UI with just the "Strategies" title.

## BUG-002: Preview Personas young_saver and retired_couple Return 400 Error
New personas returned 400 error because PreviewController wasn't uploaded to production.
Added missing personas to VALID_PERSONAS constant.

## DATA-001: Preview Personas Missing Health Data, Beneficiaries, and Proper Expenses
Preview personas lacked health status, smoking status, and beneficiary data.
Updated all persona JSON files with complete health and beneficiary information.

## UI-002: Beneficiaries Tab Showing on Non-Life Insurance Policies
Beneficiaries tab appeared on critical illness and income protection policies.
Restricted tab to life insurance policies only.

## DATA-002: Preview Personas Missing Education Level
Education level field was missing from preview persona data.
Added education_level to all persona JSON files.

## BUG-003: Risk Tab Detail View Back Button Goes to Investments Instead of Risk Dashboard
Back button in risk detail navigated to wrong page.
Fixed navigation to return to Risk dashboard.

## DATA-003: Alex Morgan (young_saver) Missing Health Data and Expenses
Young saver persona had incomplete profile data.
Added health status, expenses, and other missing fields.

## BUG-004: Retirement Navigation - Missing Back Buttons and Sidebar Reset
Retirement module had inconsistent navigation and sidebar state issues.
Added back buttons and fixed sidebar reset on navigation.

## BUG-005: Retirement Detail Tabs Not Scrolling to Top
Switching tabs in retirement detail didn't scroll to top.
Added scroll-to-top behaviour on tab change.

## BUG-006: Remove Orange Retirement Line from Monte Carlo Chart
Orange line on Monte Carlo chart was confusing users.
Removed the line for clearer visualisation.

## BUG-007: Onboarding Welcome Page Text and Styling Updates
Welcome page text needed refinement and styling improvements.
Updated copy and applied consistent styling.

## BUG-008: Date of Birth Validation Missing Across Application
Date of birth fields lacked proper validation.
Added validation rules across all DOB input fields.

## UI-003: Replace "Recommendations" with "Strategies" in Onboarding
Terminology inconsistency between "recommendations" and "strategies".
Standardised to "Strategies" throughout onboarding.

## UI-004: Update Country Dropdown in Domicile Information
Country dropdown needed updating for domicile section.
Updated dropdown options and default selection.

## UI-005: Update Domicile Information to Handle UK Constituent Countries
Domicile form didn't properly handle England, Scotland, Wales, NI.
Added UK constituent country selection logic.

## UI-006: Domicile Data Not Being Seeded for Preview Personas
Preview personas missing domicile information.
Added domicile fields to persona seeder.

## UI-007: Update Retirement Info Message in Onboarding Assets Step
Retirement info message in onboarding was unclear.
Updated copy to be more helpful and accurate.

## UI-008: Update Property and Cash Tab Info Messages in Onboarding
Property and cash tab messages needed improvement.
Updated info messages for clarity.

## UI-009: Update Investment Tab Info Message in Onboarding
Investment tab info message was outdated.
Refreshed copy to match current functionality.

## UI-010: Change Mortgage "Maturity Date" Label to "End Date"
"Maturity Date" label was confusing for users.
Changed to "End Date" for clarity.

## UI-011: Update Liabilities and Protection Info Messages in Onboarding
Liabilities and protection messages needed updates.
Improved copy for better user guidance.

## UI-012: Change Expenditure Note Box to Green
Expenditure note box was using wrong colour.
Changed to green for consistency with other info boxes.

## UI-013: Change Will Information Important Box to Green
Will information box colour was inconsistent.
Updated to green to match design system.

## UI-014: Hide Complete Setup Button for Preview Users
Complete setup button appeared for preview users inappropriately.
Hidden button when in preview mode.

## UI-015: Remove Add Data Dropdown from Navigation
Add Data dropdown in navigation was redundant.
Removed for cleaner navigation.

## BUG-009: Family Member Form Hangs on Validation Error
Family member form became unresponsive on validation errors.
Fixed form state handling on validation failure.

## BUG-010: Child Date of Birth Validation Inverted
Child DOB validation logic was backwards.
Corrected validation to allow valid dates.

## UI-016: Change 2FA Reminder Elements to Solid Green
2FA reminder styling was inconsistent.
Updated to solid green for visibility.

## UI-017: Improve Login Error Message with Registration Link
Login error message didn't help unregistered users.
Added link to registration page in error message.

## BUG-011: Property/Mortgage Display Issues
Property and mortgage values displaying incorrectly.
Fixed calculation and display logic.

## BUG-012: CRITICAL - User Data Leakage Between Sessions
User data from previous sessions could leak to new users.
Implemented proper session isolation and clearing.

## BUG-013: Expenditure Form Property Expenses Using Wrong Ownership Percentage
Property expenses calculated with wrong ownership share.
Fixed to use correct ownership percentage.

## UI-018: Display Mortgage Ownership Percentage When Different From Property
Mortgage ownership wasn't shown when different from property ownership.
Added display of mortgage-specific ownership percentage.

## BUG-014: Mortgage Ownership Not Saved - Wrong Values in Detail and Expenditure Views
Mortgage ownership percentage not persisting correctly.
Fixed save logic and display in detail views.

## BUG-015: Estate Planning Module Showing 100% of Joint Mortgages
Joint mortgages showing full value instead of user's share.
Fixed to display ownership-adjusted values.

## BUG-006: Joint Ownership Not Using Correct Percentage in Balance Sheet and Estate Planning
Joint assets using wrong percentage in calculations.
Fixed ownership percentage handling across modules.

## UI-001: Remove Taxes Tab from Property Detail Views
Taxes tab in property detail was unnecessary.
Removed tab for cleaner interface.

## UI-002: Add Blue Note for Shared Ownership Costs
Shared ownership costs lacked explanation.
Added blue info note explaining cost sharing.

## UI-003: Add Required Field Indicators to Property Form
Required fields not clearly marked on property form.
Added asterisk indicators for required fields.

## BUG-007: 2FA Prompts Not Disappearing After Enabling MFA
2FA setup prompts persisted after MFA was enabled.
Fixed prompt visibility logic.

## BUG-008: Investment Account Shows "Unnamed Account" on Dashboard
Investment accounts without names showed "Unnamed Account".
Fixed to use provider name as fallback.

## BUG-009: Tax Treatment Section Empty in Investment Detail View
Tax treatment section showed nothing for some accounts.
Fixed data loading for tax treatment display.

## UI-004: Tax Treatment Cards Have Black Border and Pastel Colors
Tax treatment card styling was inconsistent.
Updated to match design system colours.

## UI-005: Investment Account Form - NS&I Auto-Population and ISA Ownership
NS&I accounts needed auto-population and ISA ownership fixes.
Implemented auto-fill and corrected ownership logic.

## UI-007: Cash Dashboard Empty State Add Account Buttons
Empty state on cash dashboard lacked action buttons.
Added "Add Account" buttons to empty state.

## UI-008: Remove Goals Card from Cash Dashboard
Goals card on cash dashboard was redundant.
Removed card for cleaner layout.

## UI-009: Change Beta Version Message to Green
Beta version message colour was inconsistent.
Changed to green for consistency.

## BUG-014: Monte Carlo Charts Starting at Wrong Values and Year
Monte Carlo simulation started from incorrect baseline.
Fixed starting values and year calculation.

## BUG-015: Risk Badge Showing When No Risk Profile Set
Risk badge appeared even without a risk profile.
Hidden badge when no profile exists.

## BUG-016: Investment Detail Cards Show Empty State Instead of Prompts
Investment cards showed blank state instead of helpful prompts.
Added appropriate prompt messages.

## BUG-017: Retirement Strategies Section Not Showing
Retirement strategies section was hidden incorrectly.
Fixed visibility logic for strategies.

## BUG-018: IHT Calculation Table Collapsible Projection Columns
IHT table columns weren't collapsible on mobile.
Added responsive collapse functionality.

## BUG-019: Junior ISA Beneficiary Selection and NS&I Form Improvements
Junior ISA beneficiary selection had issues, NS&I form needed work.
Fixed beneficiary logic and improved NS&I form.

## BUG-020: Cash Dashboard - Real User View with Open Banking Promo
Cash dashboard needed Open Banking promotion for real users.
Added promo section for non-preview users.

## FEATURE-001: Password Reset Functionality
Application lacked password reset capability.
Implemented full password reset flow with email verification.

## BUG-007: Postcode Lookup Returning 404 Errors
Postcode lookup API calls returning 404.
Fixed API endpoint routing and configuration.

## BUG-008: Pension Projections Ignoring Percentage-Based Contributions
Pension projections used flat amounts instead of salary percentages.
Fixed to calculate contributions from salary percentage.

## BUG-009: Preview Mode Security Flaw - Session Not Cleared
Previous user session data leaked into preview mode.
Added session clearing on preview login.

## UI-001: Remove Diversification Tab from Pension Detail View
Diversification tab on pension detail was unnecessary.
Removed tab, keeping Overview, Projections, Documents.

## BUG-010: Compound Projection Growth Rate Using 500% Instead of 5%
Compound projection used percentage as decimal (5 instead of 0.05).
Fixed growth rate calculation to divide by 100.

## UI-019: ExpenditureForm Household Total Not Summing User and Spouse
Household total only showed user's expenditure for married couples.
Fixed to always sum user + spouse expenditure.

## DATA-004: Persona Data Fixes - Realistic Disposable Income and 50/50 Expenditure Split
All personas showed monthly deficits, unrealistic for demos.
Fixed 50/50 expenditure split and adjusted incomes/expenses for positive disposable income.

## BUG-011: Login Blocked by PreviewWriteInterceptor Middleware
Login failed when user had stale preview token in localStorage.
Added api/auth/login to PreviewWriteInterceptor excluded routes.

---

# 23 January 2026

## BUG-012: Cash Dashboard Not Displaying Accounts for Real Users
Real users' savings/current accounts were created in the database but never displayed in the UI.
Fixed data loading to show accounts for non-preview users.

## BUG-013: "Enter Holdings" Links Not Working in Investment Detail View
"Enter Holdings" links in Diversification and Rebalancing cards did nothing when clicked.
Added missing event listener and HoldingForm auto-selects the current account.

## BUG-014: Detail View Monte Carlo Chart Inconsistent with Dashboard
Monte Carlo chart in detail view used different probability bands and colours than dashboard.
Fixed to match dashboard (4 bands: 95%, 90%, 85%, 80% with blue-to-green colours).

## BUG-015: Account Strategy Card Position Inconsistent with Dashboard
Account Strategy Card was in left sidebar instead of full-width below chart.
Moved to full-width position matching the dashboard pattern.

## BUG-016: IHT Calculation Table Gap and -£0 Liability Values
Table had hardcoded colspan causing gaps and floating -£0 values in liabilities.
Fixed dynamic colspan, added formatLiability method, corrected v-if conditions.

## FEATURE-001: IHT Table Concertina/Accordion
Added collapsible groupings to IHT tables so multi-item asset/liability types collapse under summary headings.
Sections start collapsed by default, reducing table clutter.

## FEATURE-002: IHT Table Section-Level Concertina
Added outer section-level concertina ("User's Assets", "Spouse's Liabilities", etc.).
Section headers collapse to show subtotals, giving users three levels of detail.

## FEATURE-003: IHT Ownership Labels (Tenancy in Common and Mortgage Percentages)
Enhanced ownership labels in IHT tables.
Tenancy in Common assets show percentage, joint mortgages show actual split when not 50/50.

## FEATURE-004: Rename "Chattels" to "Personal Valuables"
All user-facing instances of "Chattel/Chattels" renamed to "Personal Valuable/Personal Valuables".
Internal code unchanged.

## BUG-017: Platform Fee Fields Not Persisting to Database
Platform fee values did not persist when adding/editing investment accounts.
Added missing fields to backend validation rules.

## BUG-018: Platform Fee Value Lost When Toggling %/£ Type
Entering a platform fee value then changing between % and £ caused the value to disappear.
Fixed value transfer between fee type fields on toggle.

## BUG-019: Fixed (£) Platform Fees Not Displayed in Fee Cards or Calculations
Fixed platform fees showed 0.00% in fee cards and were ignored in projections.
Updated all fee display and calculation logic to handle fixed fees.

## BUG-020: Detail View Navigates Away After Editing Account and Fee Card Not Refreshing
Editing an account navigated back to dashboard, and fee card didn't refresh.
Fixed to stay on detail view and reload data after update.

## BUG-021: Backend FeeAnalyzer Not Calculating Fixed (£) Platform Fees for Recommendations
Fixed fees above 0.8% threshold did not trigger "Review Platform Fees" recommendation.
Updated FeeAnalyzer to convert fixed fees to percentage equivalent for comparison.

## BUG-018: Inconsistent Form Button Scroll Behaviour
Form modals had inconsistent scroll behaviour — some sticky footers, some clipped buttons.
Standardised all 16 forms to max-h-[90vh] overflow-y-auto with buttons inside scroll container.

## BUG-019: Onboarding Personal Information Validation
Clicking Continue without entering data showed vague error with no field indication.
Added red asterisks on required fields and specific inline error messages.

## FEATURE-002: Onboarding Employment & Income Redesign
Employment & Income step showed all fields regardless of status.
Redesigned to be context-aware — fields and income sources now dynamic per employment status.

## BUG-020: Onboarding Asset Cards Open Add Form Instead of Edit Form
Clicking existing pension/savings cards opened Add form instead of Edit form.
Fixed by deriving edit mode from data prop presence (matching working AccountForm pattern).

## BUG-021: Health & Lifestyle Fields Block Onboarding Step Progress
Empty Health & Lifestyle dropdowns failed MySQL enum validation, blocking progress.
Backend now only includes these fields when a valid value is selected.

## FEATURE-003: Property Form Ownership Split Notes
Users entering joint property costs had no guidance on whether to enter full or shared amounts.
Added contextual info messages for Costs and BTL tabs explaining 100% entry with split method.

## BUG-022: Rental Income Not Applying Ownership Percentage for Joint/TiC Properties
Rental income showed 100% regardless of ownership type.
Fixed backend and frontend to apply ownership percentage for joint/tenants-in-common.

## FEATURE-004: Consistent Button Styling Across Forms
Button styling inconsistent between property, savings, and investment forms.
Standardised: blue submit buttons, green mortgage checkbox, right-aligned with Cancel/Submit order.

## FEATURE-005: Remove NI Number and Annual Income from Family Member Form
NI Number and Annual Income fields were unnecessary at family member level.
Removed from template, data, and submit logic.

## BUG-023: Dashboard State Pension Showing for All Users Without Pension Data
State Pension line (£11,500/yr default) showed for all users regardless of pension data.
Now only shows when user has entered at least one pension (DC, DB, or state).

## FEATURE-006: Remove Regular Savings from Expenses Form
Regular Savings input in Other Expenses was duplicating data captured in Savings module.
Removed from both user and spouse forms, excluded from monthly totals.

## FEATURE-007: Savings Account Access Type Auto-Selection
Selecting Notice Account or Fixed Term product type required manual access type selection.
Access type now auto-sets to match the product type.

## FEATURE-008: Onboarding Property Address Auto-Population
Users had to re-enter address when adding Main Residence during onboarding.
Address fields now auto-populate from Personal Details when Main Residence is selected.

## BUG-024: Investment Account Ownership Fields Not Persisting on Update
Changing ownership type on an investment account didn't save.
Added ownership_type, ownership_percentage, joint_owner_id to updateAccount validation rules.

## BUG-025: Savings Account Ownership Fields Not Persisting on Update
Same issue as BUG-024 but for savings accounts.
Added ownership fields to UpdateSavingsAccountRequest validation rules.

## FEATURE-009: Spouse Account Non-Editable in Family Section
Spouse accounts could be edited/deleted from primary user's account.
Removed edit/delete buttons for spouse members, added info message about linked account access.
