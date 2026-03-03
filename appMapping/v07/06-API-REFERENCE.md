# 06 - API Reference

This chapter documents every API route in the Fynla application. All routes are defined in `routes/api.php` unless otherwise noted. The base path for all API routes is `/api/`.

**Conventions used in this document:**

- **Auth** column: `public` means no authentication required; `sanctum` means the route requires a valid Sanctum bearer token via the `auth:sanctum` middleware.
- **Rate Limit** column: Shown as `requests/minutes`. A dash means the route uses the default API throttle only (60 requests per minute). Named limiters are noted where applied.
- **CRUD** shorthand: Where a resource follows standard CRUD patterns, individual routes are listed explicitly.

---

## Authentication

Prefix: `/auth`

### Public Routes

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/auth/register` | AuthController@register | public | 5/1 | Register a new user account |
| POST | `/auth/login` | AuthController@login | public | 5/1 | Login with email and password |
| POST | `/auth/verify-code` | AuthController@verifyCode | public | 10/1 | Verify the 6-digit email verification code |
| POST | `/auth/resend-code` | AuthController@resendCode | public | 5/1 | Resend the email verification code |
| POST | `/auth/logout-beacon` | AuthController@logoutBeacon | public | 10/1 | Logout via sendBeacon API on tab close |
| POST | `/auth/mfa/verify` | MFAController@verify | public | 10/1 | Verify a TOTP code during login |
| POST | `/auth/mfa/recovery` | MFAController@useRecoveryCode | public | 5/1 | Use an MFA recovery code during login |

### Password Reset (Public)

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/auth/password-reset/request` | PasswordResetController@request | public | 3/1 | Request a password reset email |
| POST | `/auth/password-reset/verify-email` | PasswordResetController@verifyEmail | public | 10/1 | Verify the reset email code |
| POST | `/auth/password-reset/resend-code` | PasswordResetController@resendCode | public | 5/1 | Resend the reset verification code |
| POST | `/auth/password-reset/verify-mfa` | PasswordResetController@verifyMfa | public | 10/1 | Verify MFA during password reset |
| POST | `/auth/password-reset/mfa-recovery` | PasswordResetController@useMfaRecovery | public | 5/1 | Use a recovery code during password reset |
| POST | `/auth/password-reset/reset` | PasswordResetController@reset | public | 5/1 | Complete the password reset with new password |

### Authenticated Routes

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/auth/logout` | AuthController@logout | sanctum | - | Logout and revoke the current token |
| GET | `/auth/user` | AuthController@user | sanctum | - | Get the authenticated user's details |
| POST | `/auth/change-password` | AuthController@changePassword | sanctum | 5/1 | Change the current user's password |

### MFA Management (Authenticated)

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/auth/mfa/status` | MFAController@status | sanctum | - | Get MFA enabled/disabled status |
| POST | `/auth/mfa/setup` | MFAController@setup | sanctum | - | Generate QR code and secret for MFA setup |
| POST | `/auth/mfa/verify-setup` | MFAController@verifySetup | sanctum | - | Confirm MFA setup with a TOTP code |
| POST | `/auth/mfa/disable` | MFAController@disable | sanctum | - | Disable MFA for the account |
| POST | `/auth/mfa/recovery-codes` | MFAController@regenerateRecoveryCodes | sanctum | - | Regenerate MFA recovery codes |

### Session Management (Authenticated)

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/auth/sessions/` | SessionController@index | sanctum | - | List all active sessions |
| DELETE | `/auth/sessions/{id}` | SessionController@destroy | sanctum | - | Revoke a specific session |
| DELETE | `/auth/sessions/others/all` | SessionController@destroyOthers | sanctum | - | Revoke all sessions except the current one |

---

## GDPR

Prefix: `/auth/gdpr`

All routes require `auth:sanctum`.

### Consent Management

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/auth/gdpr/consents` | GDPRController@getConsents | sanctum | - | Get current consent settings |
| PUT | `/auth/gdpr/consents` | GDPRController@updateConsents | sanctum | - | Update consent preferences |
| GET | `/auth/gdpr/consents/history` | GDPRController@getConsentHistory | sanctum | - | View consent change history |

### Data Export

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/auth/gdpr/export` | GDPRController@requestExport | sanctum | 3/hour | Request a data export |
| GET | `/auth/gdpr/export/status` | GDPRController@getExportStatus | sanctum | - | Check export processing status |
| GET | `/auth/gdpr/export/{id}/download` | GDPRController@downloadExport | sanctum | - | Download a completed export file |

### Data Erasure

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/auth/gdpr/erasure/initiate` | GDPRController@initiateErasure | sanctum | 3/min | Start the account erasure process |
| POST | `/auth/gdpr/erasure/verify` | GDPRController@verifyErasure | sanctum | 3/min | Verify erasure with confirmation code |
| POST | `/auth/gdpr/erasure/execute` | GDPRController@executeErasure | sanctum | 3/min | Execute the account erasure |
| POST | `/auth/gdpr/erasure/resend-code` | GDPRController@resendDeletionCode | sanctum | 3/min | Resend the erasure confirmation code |
| POST | `/auth/gdpr/erasure` | GDPRController@requestErasure | sanctum | 3/min | Request account erasure (legacy endpoint) |
| GET | `/auth/gdpr/erasure/status` | GDPRController@getErasureStatus | sanctum | - | Check erasure request status |
| POST | `/auth/gdpr/erasure/{id}/confirm` | GDPRController@confirmErasure | sanctum | 3/min | Confirm a pending erasure request |
| POST | `/auth/gdpr/erasure/{id}/cancel` | GDPRController@cancelErasure | sanctum | - | Cancel a pending erasure request |

---

## Preview

Prefix: `/preview`

Preview routes handle seeded test personas used for demonstration.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/preview/personas` | PreviewController@getPersonas | public | - | List available preview personas |
| POST | `/preview/login/{personaId}` | PreviewController@login | public | - | Login as a preview persona |
| POST | `/preview/switch/{personaId}` | PreviewController@switch | sanctum | - | Switch to a different preview persona |
| POST | `/preview/exit` | PreviewController@exit | sanctum | - | Exit preview mode |
| POST | `/user/seed-persona-data` | PreviewController@seedPersonaData | sanctum | - | Reseed data for the current preview persona |

---

## Onboarding

Prefix: `/onboarding`

All routes require `auth:sanctum`.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/onboarding/status` | OnboardingController@getOnboardingStatus | sanctum | - | Get current onboarding status and progress |
| POST | `/onboarding/focus-area` | OnboardingController@setFocusArea | sanctum | - | Set the user's primary financial focus area |
| GET | `/onboarding/steps` | OnboardingController@getSteps | sanctum | - | List all onboarding steps with status |
| GET | `/onboarding/step/{step}` | OnboardingController@getStepData | sanctum | - | Get data for a specific onboarding step |
| POST | `/onboarding/step` | OnboardingController@saveStepProgress | sanctum | - | Save progress on a step |
| POST | `/onboarding/skip-step` | OnboardingController@skipStep | sanctum | - | Skip an onboarding step |
| GET | `/onboarding/skip-reason/{step}` | OnboardingController@getSkipReason | sanctum | - | Get the recorded skip reason for a step |
| POST | `/onboarding/complete` | OnboardingController@completeOnboarding | sanctum | - | Mark onboarding as complete |
| POST | `/onboarding/restart` | OnboardingController@restartOnboarding | sanctum | - | Restart onboarding from the beginning |

---

## User Profile

Prefix: `/user`

All routes require `auth:sanctum`.

### Profile

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/user/profile` | UserProfileController@getProfile | sanctum | - | Get the full user profile |
| PUT | `/user/profile/personal` | UserProfileController@updatePersonalInfo | sanctum | - | Update personal information (name, DOB, etc.) |
| PUT | `/user/profile/income-occupation` | UserProfileController@updateIncomeOccupation | sanctum | - | Update income and occupation details |
| PUT | `/user/profile/expenditure` | UserProfileController@updateExpenditure | sanctum | - | Update expenditure information |
| PUT | `/user/profile/domicile` | UserProfileController@updateDomicileInfo | sanctum | - | Update domicile and residency details |
| GET | `/user/profile/completeness` | ProfileCompletenessController@check | sanctum | - | Get profile completeness percentage and gaps |

### Financial Commitments

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/user/financial-commitments` | UserProfileController@getFinancialCommitments | sanctum | - | Get the user's financial commitments |
| GET | `/user/spouse/financial-commitments` | UserProfileController@getSpouseFinancialCommitments | sanctum | - | Get the spouse's financial commitments |

### Dashboard Widget Order

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| PUT | `/user/dashboard-widget-order` | UserProfileController@updateDashboardWidgetOrder | sanctum | - | Save the user's preferred dashboard widget order |

### Letter to Spouse

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/user/letter-to-spouse` | LetterToSpouseController@show | sanctum | - | Get the letter to spouse content |
| GET | `/user/letter-to-spouse/exists` | LetterToSpouseController@exists | sanctum | - | Check whether a letter to spouse exists |
| GET | `/user/letter-to-spouse/spouse` | LetterToSpouseController@showSpouse | sanctum | - | Get the spouse's letter content |
| PUT | `/user/letter-to-spouse` | LetterToSpouseController@update | sanctum | - | Create or update the letter to spouse |

### Family Members

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/user/family-members/` | FamilyMembersController@index | sanctum | - | List all family members |
| POST | `/user/family-members/` | FamilyMembersController@store | sanctum | - | Add a family member |
| GET | `/user/family-members/{id}` | FamilyMembersController@show | sanctum | - | Get a specific family member |
| PUT | `/user/family-members/{id}` | FamilyMembersController@update | sanctum | - | Update a family member |
| DELETE | `/user/family-members/{id}` | FamilyMembersController@destroy | sanctum | - | Remove a family member |

### Personal Accounts

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/user/personal-accounts/` | PersonalAccountsController@index | sanctum | - | List personal accounts (income/expenditure line items) |
| POST | `/user/personal-accounts/calculate` | PersonalAccountsController@calculate | sanctum | - | Calculate personal accounts totals |
| POST | `/user/personal-accounts/line-item` | PersonalAccountsController@storeLineItem | sanctum | - | Add a line item |
| PUT | `/user/personal-accounts/line-item/{id}` | PersonalAccountsController@updateLineItem | sanctum | - | Update a line item |
| DELETE | `/user/personal-accounts/line-item/{id}` | PersonalAccountsController@destroyLineItem | sanctum | - | Delete a line item |

### Guidance Status

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/user/guidance-status` | PreviewController@getGuidanceStatus | sanctum | - | Get guidance tooltip display status |
| POST | `/user/guidance-status` | PreviewController@updateGuidanceStatus | sanctum | - | Update guidance tooltip display status |

---

## Dashboard

Prefix: `/dashboard`

All routes require `auth:sanctum`.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/dashboard/` | DashboardController@index | sanctum | - | Get full dashboard data (all modules) |
| GET | `/dashboard/financial-health-score` | DashboardController@financialHealthScore | sanctum | - | Get the composite financial health score |
| GET | `/dashboard/alerts` | DashboardController@alerts | sanctum | - | Get active dashboard alerts |
| POST | `/dashboard/alerts/{id}/dismiss` | DashboardController@dismissAlert | sanctum | - | Dismiss a specific alert |
| POST | `/dashboard/invalidate-cache` | DashboardController@invalidateCache | sanctum | - | Force refresh of cached dashboard data |

---

## Net Worth

Prefix: `/net-worth`

All routes require `auth:sanctum`.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/net-worth/overview` | NetWorthController@getOverview | sanctum | - | Get net worth summary (total assets, liabilities, net) |
| GET | `/net-worth/breakdown` | NetWorthController@getBreakdown | sanctum | - | Get net worth broken down by category |
| GET | `/net-worth/trend` | NetWorthController@getTrend | sanctum | - | Get historical net worth trend data |
| GET | `/net-worth/assets-summary` | NetWorthController@getAssetsSummary | sanctum | - | Get summary of all assets by type |
| GET | `/net-worth/assets-summary-detailed` | NetWorthController@getAssetsSummaryWithDetails | sanctum | - | Get detailed assets summary with individual items |
| GET | `/net-worth/joint-assets` | NetWorthController@getJointAssets | sanctum | - | Get jointly owned assets with ownership splits |
| POST | `/net-worth/refresh` | NetWorthController@refresh | sanctum | - | Recalculate net worth from current data |

---

## Properties

Prefix: `/properties`

All routes require `auth:sanctum`.

### Properties

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/properties/` | PropertyController@index | sanctum | - | List all properties |
| POST | `/properties/` | PropertyController@store | sanctum | - | Add a new property |
| GET | `/properties/{id}` | PropertyController@show | sanctum | - | Get a specific property |
| PUT | `/properties/{id}` | PropertyController@update | sanctum | - | Update a property |
| DELETE | `/properties/{id}` | PropertyController@destroy | sanctum | - | Delete a property |

### Property Calculations

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/properties/calculate-sdlt` | PropertyController@calculateSDLT | sanctum | - | Calculate Stamp Duty Land Tax |
| POST | `/properties/{id}/calculate-cgt` | PropertyController@calculateCGT | sanctum | - | Calculate Capital Gains Tax for a property |
| POST | `/properties/{id}/rental-income-tax` | PropertyController@calculateRentalIncomeTax | sanctum | - | Calculate rental income tax liability |

### Mortgages (nested under properties)

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/properties/{propertyId}/mortgages/` | MortgageController@index | sanctum | - | List mortgages for a property |
| POST | `/properties/{propertyId}/mortgages/` | MortgageController@store | sanctum | - | Add a mortgage to a property |
| PUT | `/properties/{propertyId}/mortgages/{mortgageId}` | MortgageController@update | sanctum | - | Update a mortgage |
| DELETE | `/properties/{propertyId}/mortgages/{mortgageId}` | MortgageController@destroy | sanctum | - | Delete a mortgage |

---

## Mortgages

Prefix: `/mortgages`

All routes require `auth:sanctum`. These routes provide direct mortgage access without the property prefix.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/mortgages/{id}` | MortgageController@show | sanctum | - | Get a specific mortgage |
| PUT | `/mortgages/{id}` | MortgageController@update | sanctum | - | Update a mortgage |
| DELETE | `/mortgages/{id}` | MortgageController@destroy | sanctum | - | Delete a mortgage |
| GET | `/mortgages/{id}/amortization-schedule` | MortgageController@amortizationSchedule | sanctum | - | Get the full amortisation schedule |
| POST | `/mortgages/calculate-payment` | MortgageController@calculatePayment | sanctum | - | Calculate monthly payment for given terms |

---

## Business Interests

Prefix: `/business-interests`

All routes require `auth:sanctum`.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/business-interests/` | BusinessInterestController@index | sanctum | - | List all business interests |
| POST | `/business-interests/` | BusinessInterestController@store | sanctum | - | Add a business interest |
| GET | `/business-interests/{id}` | BusinessInterestController@show | sanctum | - | Get a specific business interest |
| PUT | `/business-interests/{id}` | BusinessInterestController@update | sanctum | - | Update a business interest |
| DELETE | `/business-interests/{id}` | BusinessInterestController@destroy | sanctum | - | Delete a business interest |
| GET | `/business-interests/{id}/tax-deadlines` | BusinessInterestController@taxDeadlines | sanctum | - | Get tax deadlines for a business |
| GET | `/business-interests/{id}/exit-calculation` | BusinessInterestController@exitCalculation | sanctum | - | Calculate exit/sale value and tax |

---

## Chattels

Prefix: `/chattels`

All routes require `auth:sanctum`.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/chattels/` | ChattelController@index | sanctum | - | List all chattels (valuable possessions) |
| POST | `/chattels/` | ChattelController@store | sanctum | - | Add a chattel |
| GET | `/chattels/{id}` | ChattelController@show | sanctum | - | Get a specific chattel |
| PUT | `/chattels/{id}` | ChattelController@update | sanctum | - | Update a chattel |
| DELETE | `/chattels/{id}` | ChattelController@destroy | sanctum | - | Delete a chattel |
| POST | `/chattels/{id}/calculate-cgt` | ChattelController@calculateCGT | sanctum | - | Calculate Capital Gains Tax on a chattel |

---

## Protection

Prefix: `/protection`

All routes require `auth:sanctum`.

### Analysis

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/protection/` | ProtectionController@index | sanctum | - | Get protection module overview |
| POST | `/protection/analyze` | ProtectionController@analyze | sanctum | - | Run full protection needs analysis |
| GET | `/protection/recommendations` | ProtectionController@recommendations | sanctum | - | Get protection recommendations |
| POST | `/protection/scenarios` | ProtectionController@scenarios | sanctum | - | Run what-if protection scenarios |
| GET | `/protection/comprehensive-plan` | ProtectionController@getComprehensiveProtectionPlan | sanctum | - | Get the full protection plan |

### Profile

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/protection/profile` | ProtectionController@storeProfile | sanctum | - | Create or update the protection profile |
| PATCH | `/protection/profile/has-no-policies` | ProtectionController@updateHasNoPolicies | sanctum | - | Mark the user as having no protection policies |

### Life Policies

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/protection/policies/life/` | LifePolicyController@store | sanctum | - | Add a life insurance policy |
| PUT | `/protection/policies/life/{id}` | LifePolicyController@update | sanctum | - | Update a life insurance policy |
| DELETE | `/protection/policies/life/{id}` | LifePolicyController@destroy | sanctum | - | Delete a life insurance policy |

### Critical Illness Policies

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/protection/policies/critical-illness/` | CriticalIllnessPolicyController@store | sanctum | - | Add a critical illness policy |
| PUT | `/protection/policies/critical-illness/{id}` | CriticalIllnessPolicyController@update | sanctum | - | Update a critical illness policy |
| DELETE | `/protection/policies/critical-illness/{id}` | CriticalIllnessPolicyController@destroy | sanctum | - | Delete a critical illness policy |

### Income Protection Policies

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/protection/policies/income-protection/` | IncomeProtectionPolicyController@store | sanctum | - | Add an income protection policy |
| PUT | `/protection/policies/income-protection/{id}` | IncomeProtectionPolicyController@update | sanctum | - | Update an income protection policy |
| DELETE | `/protection/policies/income-protection/{id}` | IncomeProtectionPolicyController@destroy | sanctum | - | Delete an income protection policy |

### Disability Policies

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/protection/policies/disability/` | DisabilityPolicyController@store | sanctum | - | Add a disability policy |
| PUT | `/protection/policies/disability/{id}` | DisabilityPolicyController@update | sanctum | - | Update a disability policy |
| DELETE | `/protection/policies/disability/{id}` | DisabilityPolicyController@destroy | sanctum | - | Delete a disability policy |

### Sickness and Illness Policies

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/protection/policies/sickness-illness/` | SicknessIllnessPolicyController@store | sanctum | - | Add a sickness/illness policy |
| PUT | `/protection/policies/sickness-illness/{id}` | SicknessIllnessPolicyController@update | sanctum | - | Update a sickness/illness policy |
| DELETE | `/protection/policies/sickness-illness/{id}` | SicknessIllnessPolicyController@destroy | sanctum | - | Delete a sickness/illness policy |

---

## Savings

Prefix: `/savings`

All routes require `auth:sanctum`.

### Analysis

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/savings/` | SavingsController@index | sanctum | - | Get savings module overview |
| POST | `/savings/analyze` | SavingsController@analyze | sanctum | - | Run savings analysis |
| GET | `/savings/recommendations` | SavingsController@recommendations | sanctum | - | Get savings recommendations |
| POST | `/savings/scenarios` | SavingsController@scenarios | sanctum | - | Run what-if savings scenarios |
| GET | `/savings/isa-allowance/{taxYear}` | SavingsController@isaAllowance | sanctum | - | Get ISA allowance usage for a tax year |

### Accounts

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/savings/accounts/` | SavingsAccountController@store | sanctum | - | Add a savings account |
| GET | `/savings/accounts/{id}` | SavingsAccountController@show | sanctum | - | Get a specific savings account |
| PUT | `/savings/accounts/{id}` | SavingsAccountController@update | sanctum | - | Update a savings account |
| DELETE | `/savings/accounts/{id}` | SavingsAccountController@destroy | sanctum | - | Delete a savings account |
| PATCH | `/savings/accounts/{id}/toggle-retirement` | SavingsAccountController@toggleRetirementInclusion | sanctum | - | Toggle whether the account is included in retirement projections |

### Savings Goals

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/savings/goals/` | SavingsGoalController@index | sanctum | - | List all savings goals |
| POST | `/savings/goals/` | SavingsGoalController@store | sanctum | - | Create a savings goal |
| PUT | `/savings/goals/{id}` | SavingsGoalController@update | sanctum | - | Update a savings goal |
| DELETE | `/savings/goals/{id}` | SavingsGoalController@destroy | sanctum | - | Delete a savings goal |
| PATCH | `/savings/goals/{id}/progress` | SavingsGoalController@updateGoalProgress | sanctum | - | Update progress towards a savings goal |

---

## Goals

Prefix: `/goals`

All routes require `auth:sanctum`.

### Overview and Analysis

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/goals/` | GoalsController@index | sanctum | - | List all goals |
| GET | `/goals/analysis` | GoalsController@analysis | sanctum | - | Get goals analysis |
| GET | `/goals/dashboard-overview` | GoalsController@dashboardOverview | sanctum | - | Get goals summary for the dashboard |
| GET | `/goals/projection` | GoalsController@getProjection | sanctum | - | Get overall goals projection |
| GET | `/goals/household-summary` | GoalsController@getHouseholdSummary | sanctum | - | Get household-level goals summary |
| GET | `/goals/types` | GoalsController@getGoalTypes | sanctum | - | List available goal types |
| GET | `/goals/risk-levels` | GoalsController@getRiskLevels | sanctum | - | List available risk levels for goals |
| POST | `/goals/calculate-property-costs` | GoalsController@calculatePropertyCosts | sanctum | - | Calculate costs for a property purchase goal |

### Goal CRUD

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/goals/` | GoalsController@store | sanctum | - | Create a new goal |
| GET | `/goals/{id}` | GoalsController@show | sanctum | - | Get a specific goal |
| PUT | `/goals/{id}` | GoalsController@update | sanctum | - | Update a goal |
| DELETE | `/goals/{id}` | GoalsController@destroy | sanctum | - | Delete a goal |

### Goal Details

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/goals/{id}/contribution` | GoalsController@recordContribution | sanctum | - | Record a contribution towards a goal |
| GET | `/goals/{id}/projections` | GoalsController@getProjections | sanctum | - | Get projections for a specific goal |
| GET | `/goals/{id}/scenarios` | GoalsController@getScenarios | sanctum | - | Get what-if scenarios for a goal |
| GET | `/goals/{id}/contributions` | GoalsController@getContributionHistory | sanctum | - | Get contribution history for a goal |

---

## Life Events

Prefix: `/life-events`

All routes require `auth:sanctum`.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/life-events/` | LifeEventsController@index | sanctum | - | List all life events |
| GET | `/life-events/types` | LifeEventsController@getEventTypes | sanctum | - | List available life event types |
| GET | `/life-events/by-age` | LifeEventsController@getByAge | sanctum | - | Get life events organised by age |
| POST | `/life-events/` | LifeEventsController@store | sanctum | - | Create a life event |
| GET | `/life-events/{id}` | LifeEventsController@show | sanctum | - | Get a specific life event |
| PUT | `/life-events/{id}` | LifeEventsController@update | sanctum | - | Update a life event |
| DELETE | `/life-events/{id}` | LifeEventsController@destroy | sanctum | - | Delete a life event |
| POST | `/life-events/{id}/complete` | LifeEventsController@markCompleted | sanctum | - | Mark a life event as completed |

---

## Investment

Prefix: `/investment`

All routes require `auth:sanctum`. The investment module has the most routes of any module, covering portfolio analysis, optimisation, rebalancing, and tax-efficient strategies.

### Core Analysis

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/investment/` | InvestmentController@index | sanctum | - | Get investment module overview |
| POST | `/investment/analyze` | InvestmentController@analyze | sanctum | - | Run investment analysis |
| GET | `/investment/recommendations` | InvestmentController@recommendations | sanctum | - | Get investment recommendations |
| POST | `/investment/scenarios` | InvestmentController@scenarios | sanctum | - | Run what-if investment scenarios |

### Projections and Simulation

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/investment/monte-carlo` | InvestmentController@startMonteCarlo | sanctum | - | Start a Monte Carlo simulation job |
| GET | `/investment/monte-carlo/{jobId}` | InvestmentController@getMonteCarloResults | sanctum | - | Get results of a Monte Carlo simulation |
| POST | `/investment/projections` | InvestmentController@getProjections | sanctum | - | Get investment projections with custom parameters |

### Portfolio Strategy

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/investment/portfolio-strategy` | PortfolioStrategyController@index | sanctum | - | Get overall portfolio strategy |
| GET | `/investment/portfolio-strategy/account/{accountId}` | PortfolioStrategyController@forAccount | sanctum | - | Get portfolio strategy for a specific account |

### Accounts

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/investment/accounts/` | InvestmentAccountController@store | sanctum | - | Add an investment account |
| PUT | `/investment/accounts/{id}` | InvestmentAccountController@update | sanctum | - | Update an investment account |
| DELETE | `/investment/accounts/{id}` | InvestmentAccountController@destroy | sanctum | - | Delete an investment account |
| GET | `/investment/accounts/{id}/projections` | InvestmentAccountController@getAccountProjections | sanctum | - | Get projections for a specific account |
| GET | `/investment/accounts/{id}/rebalancing` | InvestmentAccountController@getAccountRebalancing | sanctum | - | Get rebalancing analysis for an account |
| PATCH | `/investment/accounts/{id}/rebalancing-threshold` | InvestmentAccountController@updateRebalancingThreshold | sanctum | - | Update the rebalancing drift threshold |
| GET | `/investment/accounts/{id}/diversification` | InvestmentAccountController@getAccountDiversification | sanctum | - | Get diversification analysis for an account |
| PATCH | `/investment/accounts/{id}/toggle-retirement` | InvestmentAccountController@toggleRetirementInclusion | sanctum | - | Toggle whether the account is included in retirement projections |

### Holdings

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/investment/holdings/` | InvestmentHoldingController@store | sanctum | - | Add a holding to an account |
| PUT | `/investment/holdings/{id}` | InvestmentHoldingController@update | sanctum | - | Update a holding |
| DELETE | `/investment/holdings/{id}` | InvestmentHoldingController@destroy | sanctum | - | Delete a holding |

### Portfolio Optimisation

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/investment/portfolio-optimization/efficient-frontier` | PortfolioOptimizationController@efficientFrontier | sanctum | - | Calculate the efficient frontier |
| GET | `/investment/portfolio-optimization/current-position` | PortfolioOptimizationController@currentPosition | sanctum | - | Get the current portfolio position on the frontier |
| GET | `/investment/portfolio-optimization/correlation-matrix` | PortfolioOptimizationController@correlationMatrix | sanctum | - | Get asset class correlation matrix |
| POST | `/investment/portfolio-optimization/minimize-variance` | PortfolioOptimizationController@minimizeVariance | sanctum | - | Calculate the minimum variance portfolio |
| POST | `/investment/portfolio-optimization/maximize-sharpe` | PortfolioOptimizationController@maximizeSharpe | sanctum | - | Calculate the maximum Sharpe ratio portfolio |
| POST | `/investment/portfolio-optimization/target-return` | PortfolioOptimizationController@targetReturn | sanctum | - | Calculate the optimal portfolio for a target return |
| POST | `/investment/portfolio-optimization/risk-parity` | PortfolioOptimizationController@riskParity | sanctum | - | Calculate a risk parity portfolio |
| DELETE | `/investment/portfolio-optimization/clear-cache` | PortfolioOptimizationController@clearCache | sanctum | - | Clear cached optimisation results |

### Rebalancing

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/investment/rebalancing/calculate` | RebalancingController@calculate | sanctum | - | Calculate rebalancing trades needed |
| POST | `/investment/rebalancing/from-optimization` | RebalancingController@fromOptimization | sanctum | - | Generate rebalancing from optimisation result |
| POST | `/investment/rebalancing/compare-cgt` | RebalancingController@compareCGT | sanctum | - | Compare CGT impact of rebalancing options |
| POST | `/investment/rebalancing/within-cgt-allowance` | RebalancingController@withinCGTAllowance | sanctum | - | Rebalance within the annual CGT allowance |
| POST | `/investment/rebalancing/analyze-drift` | RebalancingController@analyzeDrift | sanctum | - | Analyse portfolio drift from target allocation |
| POST | `/investment/rebalancing/evaluate-strategies` | RebalancingController@evaluateStrategies | sanctum | - | Evaluate multiple rebalancing strategies |
| POST | `/investment/rebalancing/threshold-strategy` | RebalancingController@thresholdStrategy | sanctum | - | Calculate threshold-based rebalancing strategy |
| POST | `/investment/rebalancing/calendar-strategy` | RebalancingController@calendarStrategy | sanctum | - | Calculate calendar-based rebalancing strategy |
| POST | `/investment/rebalancing/opportunistic-strategy` | RebalancingController@opportunisticStrategy | sanctum | - | Calculate opportunistic rebalancing strategy |
| POST | `/investment/rebalancing/recommend-frequency` | RebalancingController@recommendFrequency | sanctum | - | Get recommended rebalancing frequency |

### Rebalancing Actions

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/investment/rebalancing/actions` | RebalancingActionController@index | sanctum | - | List pending rebalancing actions |
| POST | `/investment/rebalancing/actions` | RebalancingActionController@store | sanctum | - | Create a rebalancing action |
| PUT | `/investment/rebalancing/actions/{id}` | RebalancingActionController@update | sanctum | - | Update a rebalancing action |
| DELETE | `/investment/rebalancing/actions/{id}` | RebalancingActionController@destroy | sanctum | - | Delete a rebalancing action |

### Tax Optimisation

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/investment/tax-optimization/analyze` | TaxOptimizationController@analyze | sanctum | - | Run full tax optimisation analysis |
| GET | `/investment/tax-optimization/isa-strategy` | TaxOptimizationController@isaStrategy | sanctum | - | Get ISA optimisation strategy |
| GET | `/investment/tax-optimization/cgt-harvesting` | TaxOptimizationController@cgtHarvesting | sanctum | - | Get CGT harvesting opportunities |
| GET | `/investment/tax-optimization/bed-and-isa` | TaxOptimizationController@bedAndIsa | sanctum | - | Get Bed and ISA transfer recommendations |
| GET | `/investment/tax-optimization/efficiency-score` | TaxOptimizationController@efficiencyScore | sanctum | - | Get portfolio tax efficiency score |
| GET | `/investment/tax-optimization/recommendations` | TaxOptimizationController@recommendations | sanctum | - | Get tax optimisation recommendations |
| POST | `/investment/tax-optimization/calculate-savings` | TaxOptimizationController@calculateSavings | sanctum | - | Calculate potential tax savings |
| DELETE | `/investment/tax-optimization/clear-cache` | TaxOptimizationController@clearCache | sanctum | - | Clear cached tax optimisation data |

### Risk Preference

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/investment/risk-preference/levels` | RiskPreferenceController@levels | sanctum | - | List all risk levels |
| GET | `/investment/risk-preference/profile` | RiskPreferenceController@getProfile | sanctum | - | Get the user's risk profile |
| POST | `/investment/risk-preference/profile` | RiskPreferenceController@storeProfile | sanctum | - | Save the user's risk profile |
| GET | `/investment/risk-preference/allowed-levels` | RiskPreferenceController@allowedLevels | sanctum | - | Get risk levels permitted for the user |
| GET | `/investment/risk-preference/config/{level}` | RiskPreferenceController@config | sanctum | - | Get configuration for a specific risk level |
| POST | `/investment/risk-preference/recalculate` | RiskPreferenceController@recalculate | sanctum | - | Recalculate the recommended risk level |
| POST | `/investment/risk-preference/validate-product-level` | RiskPreferenceController@validateProductLevel | sanctum | - | Validate whether a product risk level suits the user |

### Model Portfolios

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/investment/model-portfolios/{riskLevel}` | ModelPortfolioController@show | sanctum | - | Get the model portfolio for a risk level |
| GET | `/investment/model-portfolios/all` | ModelPortfolioController@all | sanctum | - | Get all model portfolios |
| POST | `/investment/model-portfolios/compare` | ModelPortfolioController@compare | sanctum | - | Compare multiple model portfolios |
| GET | `/investment/model-portfolios/optimize-by-age` | ModelPortfolioController@optimizeByAge | sanctum | - | Get age-optimised portfolio allocation |
| POST | `/investment/model-portfolios/optimize-by-horizon` | ModelPortfolioController@optimizeByHorizon | sanctum | - | Get horizon-optimised portfolio allocation |
| GET | `/investment/model-portfolios/glide-path` | ModelPortfolioController@glidePath | sanctum | - | Get the glide path allocation over time |
| POST | `/investment/model-portfolios/funds` | ModelPortfolioController@funds | sanctum | - | Get fund suggestions for a model portfolio |

### Efficient Frontier

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/investment/efficient-frontier/calculate` | EfficientFrontierController@calculate | sanctum | - | Calculate the efficient frontier curve |
| GET | `/investment/efficient-frontier/default` | EfficientFrontierController@default | sanctum | - | Get the default efficient frontier |
| GET | `/investment/efficient-frontier/analyze-current` | EfficientFrontierController@analyzeCurrent | sanctum | - | Analyse current portfolio against the frontier |
| POST | `/investment/efficient-frontier/optimal-by-return` | EfficientFrontierController@optimalByReturn | sanctum | - | Find the optimal portfolio for a target return |
| POST | `/investment/efficient-frontier/optimal-by-risk` | EfficientFrontierController@optimalByRisk | sanctum | - | Find the optimal portfolio for a target risk |
| POST | `/investment/efficient-frontier/compare` | EfficientFrontierController@compare | sanctum | - | Compare portfolios against the frontier |
| POST | `/investment/efficient-frontier/statistics` | EfficientFrontierController@statistics | sanctum | - | Get statistical analysis of the frontier |
| GET | `/investment/efficient-frontier/default-assumptions` | EfficientFrontierController@defaultAssumptions | sanctum | - | Get default return and risk assumptions |

### Investment Planning

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/investment/planning/generate` | InvestmentPlanningController@generate | sanctum | - | Generate a new investment plan |
| GET | `/investment/planning/` | InvestmentPlanningController@index | sanctum | - | List investment plans |
| GET | `/investment/planning/all` | InvestmentPlanningController@all | sanctum | - | Get all investment plans with details |
| GET | `/investment/planning/{id}` | InvestmentPlanningController@show | sanctum | - | Get a specific investment plan |
| DELETE | `/investment/planning/{id}` | InvestmentPlanningController@destroy | sanctum | - | Delete an investment plan |
| DELETE | `/investment/planning/clear-cache` | InvestmentPlanningController@clearCache | sanctum | - | Clear cached planning data |

---

## Retirement

Prefix: `/retirement`

All routes require `auth:sanctum`.

### Analysis and Projections

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/retirement/` | RetirementController@index | sanctum | - | Get retirement module overview |
| GET | `/retirement/projections` | RetirementController@getProjections | sanctum | - | Get retirement projections |
| GET | `/retirement/required-capital` | RetirementController@getRequiredCapital | sanctum | - | Calculate retirement capital required |
| GET | `/retirement/dc-pensions/{id}/projections` | RetirementController@getDCPensionProjection | sanctum | - | Get projections for a specific DC pension |
| POST | `/retirement/analyze` | RetirementController@analyze | sanctum | - | Run retirement analysis |
| GET | `/retirement/recommendations` | RetirementController@recommendations | sanctum | - | Get retirement recommendations |
| POST | `/retirement/scenarios` | RetirementController@scenarios | sanctum | - | Run what-if retirement scenarios |

### Portfolio Analysis

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/retirement/portfolio-analysis` | RetirementController@analyzeDCPensionPortfolio | sanctum | - | Analyse all DC pension portfolios |
| GET | `/retirement/portfolio-analysis/{dcPensionId}` | RetirementController@analyzeDCPensionPortfolioById | sanctum | - | Analyse a specific DC pension portfolio |

### Allowances and Strategies

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/retirement/annual-allowance/{taxYear}` | RetirementController@checkAnnualAllowance | sanctum | - | Check annual allowance usage for a tax year |
| GET | `/retirement/strategies` | RetirementController@getStrategies | sanctum | - | Get available retirement strategies |
| GET | `/retirement/strategies/impact` | RetirementController@calculateStrategyImpact | sanctum | - | Calculate impact of a retirement strategy |

### Retirement Income

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/retirement/income` | RetirementIncomeController@getRetirementIncome | sanctum | - | Get projected retirement income |
| POST | `/retirement/income/calculate` | RetirementIncomeController@calculateRetirementIncome | sanctum | - | Calculate retirement income with custom parameters |
| GET | `/retirement/income/accounts` | RetirementIncomeController@getIncomeAccounts | sanctum | - | List accounts contributing to retirement income |

### DC Pensions

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/retirement/pensions/dc/` | DCPensionController@store | sanctum | - | Add a defined contribution pension |
| PUT | `/retirement/pensions/dc/{id}` | DCPensionController@update | sanctum | - | Update a DC pension |
| DELETE | `/retirement/pensions/dc/{id}` | DCPensionController@destroy | sanctum | - | Delete a DC pension |
| GET | `/retirement/pensions/dc/{id}/diversification` | DCPensionController@getDCPensionDiversification | sanctum | - | Get diversification analysis for a DC pension |

### DC Pension Holdings

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/retirement/pensions/dc/{dcPensionId}/holdings` | DCPensionHoldingController@index | sanctum | - | List holdings in a DC pension |
| POST | `/retirement/pensions/dc/{dcPensionId}/holdings` | DCPensionHoldingController@store | sanctum | - | Add a holding to a DC pension |
| PUT | `/retirement/pensions/dc/{dcPensionId}/holdings/{id}` | DCPensionHoldingController@update | sanctum | - | Update a DC pension holding |
| DELETE | `/retirement/pensions/dc/{dcPensionId}/holdings/{id}` | DCPensionHoldingController@destroy | sanctum | - | Delete a DC pension holding |
| POST | `/retirement/pensions/dc/{dcPensionId}/holdings/bulk-update` | DCPensionHoldingController@bulkUpdate | sanctum | - | Bulk update holdings in a DC pension |

### DB Pensions

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/retirement/pensions/db/` | DBPensionController@store | sanctum | - | Add a defined benefit pension |
| PUT | `/retirement/pensions/db/{id}` | DBPensionController@update | sanctum | - | Update a DB pension |
| DELETE | `/retirement/pensions/db/{id}` | DBPensionController@destroy | sanctum | - | Delete a DB pension |

### State Pension

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/retirement/state-pension` | RetirementController@updateStatePension | sanctum | - | Update state pension details |

---

## Estate Planning

Prefix: `/estate`

All routes require `auth:sanctum`.

### Overview and Analysis

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/estate/` | EstateController@index | sanctum | - | Get estate planning module overview |
| GET | `/estate/net-worth` | EstateController@getNetWorth | sanctum | - | Get estate net worth for IHT purposes |
| GET | `/estate/cash-flow` | EstateController@getCashFlow | sanctum | - | Get estate cash flow analysis |
| GET | `/estate/comprehensive-plan` | EstateController@getComprehensiveEstatePlan | sanctum | - | Get the full estate plan |

### IHT Calculations

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/estate/calculate-iht` | IHTController@calculateIHT | sanctum | - | Calculate Inheritance Tax liability |
| POST | `/estate/calculate-surviving-spouse-iht` | IHTController@calculateSurvivingSpouseIHT | sanctum | - | Calculate IHT for the surviving spouse |
| POST | `/estate/calculate-second-death-iht-planning` | IHTController@calculateSecondDeathIHTPlanning | sanctum | - | Calculate IHT on second death with planning options |

### Estate Profile

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/estate/profile` | EstateController@storeOrUpdateIHTProfile | sanctum | - | Create or update the IHT profile |

### Estate Assets

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/estate/assets/` | EstateAssetController@store | sanctum | - | Add an estate asset |
| PUT | `/estate/assets/{id}` | EstateAssetController@update | sanctum | - | Update an estate asset |
| DELETE | `/estate/assets/{id}` | EstateAssetController@destroy | sanctum | - | Delete an estate asset |

### Estate Liabilities

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/estate/liabilities/` | EstateLiabilityController@store | sanctum | - | Add an estate liability |
| PUT | `/estate/liabilities/{id}` | EstateLiabilityController@update | sanctum | - | Update an estate liability |
| DELETE | `/estate/liabilities/{id}` | EstateLiabilityController@destroy | sanctum | - | Delete an estate liability |

### Gifts

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/estate/gifts/planned-strategy` | GiftController@getPlannedGiftingStrategy | sanctum | - | Get the planned gifting strategy |
| GET | `/estate/gifts/personalized-strategy` | GiftController@getPersonalizedGiftingStrategy | sanctum | - | Get a personalised gifting strategy |
| GET | `/estate/gifts/trust-strategy` | GiftController@getPersonalizedTrustStrategy | sanctum | - | Get a personalised trust-based gifting strategy |
| POST | `/estate/gifts/` | GiftController@store | sanctum | - | Record a gift |
| PUT | `/estate/gifts/{id}` | GiftController@update | sanctum | - | Update a gift record |
| DELETE | `/estate/gifts/{id}` | GiftController@destroy | sanctum | - | Delete a gift record |

### Life Policy Strategy

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/estate/life-policy-strategy` | EstateController@getLifePolicyStrategy | sanctum | - | Get life policy strategy for IHT planning |

### Trusts

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/estate/trusts/` | TrustController@index | sanctum | - | List all trusts |
| POST | `/estate/trusts/` | TrustController@store | sanctum | - | Create a trust |
| PUT | `/estate/trusts/{id}` | TrustController@update | sanctum | - | Update a trust |
| DELETE | `/estate/trusts/{id}` | TrustController@destroy | sanctum | - | Delete a trust |
| GET | `/estate/trusts/{id}/analyze` | TrustController@analyzeTrust | sanctum | - | Analyse a trust's tax implications |
| GET | `/estate/trusts/{id}/assets` | TrustController@getTrustAssets | sanctum | - | List assets held in a trust |
| POST | `/estate/trusts/{id}/calculate-iht-impact` | TrustController@calculateTrustIHTImpact | sanctum | - | Calculate the IHT impact of a trust |

### Will

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/estate/will` | WillController@getWill | sanctum | - | Get the will details |
| POST | `/estate/will` | WillController@storeOrUpdateWill | sanctum | - | Create or update the will |
| POST | `/estate/calculate-intestacy` | WillController@calculateIntestacy | sanctum | - | Calculate estate distribution under intestacy rules |

### Bequests

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/estate/bequests/` | BequestController@index | sanctum | - | List all bequests |
| POST | `/estate/bequests/` | BequestController@store | sanctum | - | Add a bequest |
| PUT | `/estate/bequests/{id}` | BequestController@update | sanctum | - | Update a bequest |
| DELETE | `/estate/bequests/{id}` | BequestController@destroy | sanctum | - | Delete a bequest |

### Discounted Gift Schemes

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/estate/calculate-discount` | EstateController@calculateDiscountedGiftDiscount | sanctum | - | Calculate the discount on a discounted gift scheme |

---

## Holistic Planning

Prefix: `/holistic`

All routes require `auth:sanctum`. The holistic module analyses the user's financial position across all modules and produces cross-cutting recommendations.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/holistic/analyze` | HolisticController@analyze | sanctum | - | Run holistic financial analysis |
| POST | `/holistic/plan` | HolisticController@plan | sanctum | - | Generate a holistic financial plan |
| GET | `/holistic/recommendations` | HolisticController@recommendations | sanctum | - | Get holistic recommendations |
| GET | `/holistic/cash-flow-analysis` | HolisticController@cashFlowAnalysis | sanctum | - | Get cross-module cash flow analysis |
| POST | `/holistic/recommendations/{id}/mark-done` | HolisticController@markRecommendationDone | sanctum | - | Mark a recommendation as done |
| POST | `/holistic/recommendations/{id}/in-progress` | HolisticController@markRecommendationInProgress | sanctum | - | Mark a recommendation as in progress |
| POST | `/holistic/recommendations/{id}/dismiss` | HolisticController@dismissRecommendation | sanctum | - | Dismiss a recommendation |
| GET | `/holistic/recommendations/completed` | HolisticController@completedRecommendations | sanctum | - | List completed recommendations |
| PATCH | `/holistic/recommendations/{id}/notes` | HolisticController@updateRecommendationNotes | sanctum | - | Update notes on a recommendation |

---

## Recommendations

Prefix: `/recommendations`

All routes require `auth:sanctum`. These routes provide a unified view of recommendations across all modules.

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/recommendations/` | RecommendationsController@index | sanctum | - | List all active recommendations |
| GET | `/recommendations/summary` | RecommendationsController@summary | sanctum | - | Get recommendation summary counts |
| GET | `/recommendations/top` | RecommendationsController@top | sanctum | - | Get top priority recommendations |
| GET | `/recommendations/completed` | RecommendationsController@completed | sanctum | - | List completed recommendations |
| POST | `/recommendations/{id}/mark-done` | RecommendationsController@markDone | sanctum | - | Mark a recommendation as done |
| POST | `/recommendations/{id}/in-progress` | RecommendationsController@markInProgress | sanctum | - | Mark a recommendation as in progress |
| POST | `/recommendations/{id}/dismiss` | RecommendationsController@dismiss | sanctum | - | Dismiss a recommendation |
| PATCH | `/recommendations/{id}/notes` | RecommendationsController@updateNotes | sanctum | - | Update notes on a recommendation |

---

## Spouse and Household

All routes require `auth:sanctum`.

### Spouse Permissions

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/spouse-permission/status` | SpousePermissionController@status | sanctum | - | Get spouse link status |
| POST | `/spouse-permission/request` | SpousePermissionController@request | sanctum | - | Send a spouse linking request |
| POST | `/spouse-permission/accept` | SpousePermissionController@accept | sanctum | - | Accept a spouse linking request |
| POST | `/spouse-permission/reject` | SpousePermissionController@reject | sanctum | - | Reject a spouse linking request |
| DELETE | `/spouse-permission/revoke` | SpousePermissionController@revoke | sanctum | - | Revoke spouse link |

### Household Data

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/users/{userId}` | UserController@getUserById | sanctum | - | Get a user by ID (used for spouse data) |
| PUT | `/users/{userId}/expenditure` | UserController@updateSpouseExpenditure | sanctum | - | Update spouse expenditure data |
| GET | `/joint-account-logs/` | JointAccountLogController@index | sanctum | - | List joint account activity logs |

---

## Settings and Administration

All routes require `auth:sanctum`.

### Assumptions

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/settings/assumptions` | AssumptionsController@index | sanctum | - | Get all financial assumptions |
| PUT | `/settings/assumptions/{type}` | AssumptionsController@update | sanctum | - | Update assumptions for a specific type |

### UK Taxes (Admin)

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/uk-taxes/` | UKTaxesController@index | sanctum | - | Get all UK tax configurations |

### Tax Settings (Admin)

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/tax-settings/` | TaxSettingsController@index | sanctum | - | List all tax settings |
| POST | `/tax-settings/` | TaxSettingsController@store | sanctum | - | Create a tax setting |
| PUT | `/tax-settings/{id}` | TaxSettingsController@update | sanctum | - | Update a tax setting |
| POST | `/tax-settings/{id}/activate` | TaxSettingsController@activate | sanctum | - | Activate a tax setting |
| POST | `/tax-settings/{id}/duplicate` | TaxSettingsController@duplicate | sanctum | - | Duplicate a tax setting |
| DELETE | `/tax-settings/{id}` | TaxSettingsController@destroy | sanctum | - | Delete a tax setting |

### Admin and Backups

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/admin/` | AdminController@index | sanctum | - | Get admin dashboard data |
| POST | `/admin/` | AdminController@store | sanctum | - | Create an admin record |
| PUT | `/admin/{id}` | AdminController@update | sanctum | - | Update an admin record |
| DELETE | `/admin/{id}` | AdminController@destroy | sanctum | - | Delete an admin record |

---

## Miscellaneous

All routes require `auth:sanctum` unless noted.

### Documents

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/documents/` | DocumentController@index | sanctum | 30/1 | List uploaded documents |
| POST | `/documents/` | DocumentController@store | sanctum | 30/1 | Upload a document |
| GET | `/documents/{id}` | DocumentController@show | sanctum | 30/1 | Get a specific document |
| POST | `/documents/{id}/confirm` | DocumentController@confirm | sanctum | 30/1 | Confirm document processing |
| POST | `/documents/{id}/reprocess` | DocumentController@reprocess | sanctum | 30/1 | Reprocess a document |
| DELETE | `/documents/{id}` | DocumentController@destroy | sanctum | 30/1 | Delete a document |

### Postcode Lookup

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/postcode-lookup/{postcode}` | PostcodeLookupController@lookup | sanctum | 30/1 | Look up addresses by UK postcode |

### Occupations

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/occupations/search` | OccupationController@search | sanctum | - | Search occupations by keyword |

### Bug Reports

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| POST | `/bug-report` | BugReportController@store | sanctum | bug-reports | Submit a bug report |

### Info Guides

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/info-guide/{slug}` | InfoGuideController@show | sanctum | - | Get an info guide by slug |
| PUT | `/info-guide/{slug}` | InfoGuideController@update | sanctum | - | Update an info guide |

### Investment-Savings Plan

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/plans/investment-savings` | InvestmentSavingsPlanController@index | sanctum | - | Get the combined investment-savings plan |
| DELETE | `/plans/investment-savings/clear-cache` | InvestmentSavingsPlanController@clearCache | sanctum | - | Clear the plan cache |

### Tax Product Information

| Method | URI | Controller@Method | Auth | Rate Limit | Purpose |
|--------|-----|-------------------|------|------------|---------|
| GET | `/tax-info/investment/{accountType}` | TaxProductInfoController@investmentAccountTax | sanctum | - | Get tax treatment for an investment account type |
| GET | `/tax-info/savings/{accountType}` | TaxProductInfoController@savingsAccountTax | sanctum | - | Get tax treatment for a savings account type |
| GET | `/tax-info/summary` | TaxProductInfoController@summary | sanctum | - | Get tax information summary across all products |

---

## Web Routes

Defined in `routes/web.php`. The application is a single-page application (SPA); all non-API routes serve the Vue.js application.

| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/{any}` | SPA catch-all. Serves the `app` Blade view. The `{any}` parameter matches any path (regex: `.*`). Vue Router handles client-side routing from this entry point. |

---

## Rate Limiting Summary

The following named rate limiters apply across the API:

| Limiter | Limit | Scope | Applied To |
|---------|-------|-------|------------|
| Default API | 60/minute | Per user | All API routes (baseline) |
| `throttle:5,1` | 5/minute | Per IP | Auth endpoints (register, login, password changes) |
| `throttle:10,1` | 10/minute | Per IP | Verification code endpoints |
| `throttle:3,1` | 3/minute | Per IP | Password reset request |
| `throttle:30,1` | 30/minute | Per user | Document uploads, postcode lookup |
| `throttle:export` | 3/hour | Per user | GDPR data exports |
| `throttle:sensitive` | 3/minute | Per user | GDPR erasure operations |
| `throttle:bug-reports` | Named limiter | Per user | Bug report submissions |
