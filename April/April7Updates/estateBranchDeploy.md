# Deploy Guide — estateDash Branch (PR #193)

**Date**: 7 April 2026  
**Branch**: `estateDash` → merged to `main` (PR #193)  
**Commit range**: `75cf43e..89ca193` (17 commits)  
**Scope**: Estate dashboard redesign, code review fixes (48/68 issues), cookie consent, spouse lifecycle

---

## 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

---

## 2. Upload PHP files to production

Upload these files via SiteGround File Manager to `~/www/fynla.org/public_html/`:

### Agents

| File | Change |
|------|--------|
| `app/Agents/CoordinatingAgent.php` | Mortgage tool, joint ownership in tool results |
| `app/Agents/EstateAgent.php` | Minor fix |
| `app/Agents/InvestmentAgent.php` | Minor fix |
| `app/Agents/RetirementAgent.php` | Minor fix |

### Console Commands

| File | Change |
|------|--------|
| `app/Console/Commands/SendPolicyRenewalReminders.php` | SQL injection fix — parameterised selectRaw |
| `app/Console/Commands/SendProtectionAlerts.php` | SQL injection fix — parameterised selectRaw |

### Constants

| File | Change |
|------|--------|
| `app/Constants/QuerySchemas.php` | New query schemas |
| `app/Constants/TaxDefaults.php` | Added DIVIDEND_*, BADR_RATE, STATE_PENSION_* constants |

### Controllers (51 files)

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/AdminController.php` | readonly constructors, envelope fixes |
| `app/Http/Controllers/Api/AiAuditController.php` | readonly constructor |
| `app/Http/Controllers/Api/AuthController.php` | readonly constructor |
| `app/Http/Controllers/Api/BusinessInterestController.php` | readonly constructor |
| `app/Http/Controllers/Api/ContactFormController.php` | readonly constructor |
| `app/Http/Controllers/Api/Estate/GiftingController.php` | readonly constructor |
| `app/Http/Controllers/Api/Estate/LifePolicyController.php` | readonly constructor |
| `app/Http/Controllers/Api/Estate/TrustController.php` | Returns TrustResource (was raw model) |
| `app/Http/Controllers/Api/Estate/WillController.php` | readonly constructor |
| `app/Http/Controllers/Api/EstateController.php` | readonly constructor |
| `app/Http/Controllers/Api/InfoGuideController.php` | readonly constructor |
| `app/Http/Controllers/Api/Investment/AssetLocationController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/EfficientFrontierController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/FeeImpactController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/GoalProgressController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/InvestmentScenarioController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/ModelPortfolioController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/PerformanceAttributionController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/PortfolioStrategyController.php` | Response envelope fix |
| `app/Http/Controllers/Api/Investment/RebalancingActionsController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/RebalancingStrategiesController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Investment/TaxOptimizationController.php` | readonly, envelope |
| `app/Http/Controllers/Api/InvestmentProjectionController.php` | readonly constructor |
| `app/Http/Controllers/Api/LetterToSpouseController.php` | readonly constructor |
| `app/Http/Controllers/Api/LifeStageController.php` | readonly constructor |
| `app/Http/Controllers/Api/MFAController.php` | readonly constructor |
| `app/Http/Controllers/Api/MortgageController.php` | Uses MortgageResource (was toArray) |
| `app/Http/Controllers/Api/OnboardingController.php` | readonly constructor |
| `app/Http/Controllers/Api/PasswordResetController.php` | readonly constructor |
| `app/Http/Controllers/Api/PaymentController.php` | Billing history envelope fix |
| `app/Http/Controllers/Api/PersonalAccountsController.php` | readonly constructor |
| `app/Http/Controllers/Api/Plans/PlanController.php` | readonly constructor |
| `app/Http/Controllers/Api/PostcodeLookupController.php` | readonly constructor |
| `app/Http/Controllers/Api/PropertyController.php` | Soft-delete cascade to mortgages |
| `app/Http/Controllers/Api/RecommendationsController.php` | readonly, envelope |
| `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php` | readonly, envelope |
| `app/Http/Controllers/Api/RetirementController.php` | readonly constructor |
| `app/Http/Controllers/Api/RiskPreferenceController.php` | readonly constructor |
| `app/Http/Controllers/Api/SavingsController.php` | Removed deprecated updateGoalProgress |
| `app/Http/Controllers/Api/Settings/AssumptionsController.php` | readonly constructor |
| `app/Http/Controllers/Api/TaxSettingsController.php` | readonly constructor |
| `app/Http/Controllers/Api/UserProfileController.php` | readonly constructor |

### Middleware & Requests

| File | Change |
|------|--------|
| `app/Http/Middleware/CheckFeatureAccess.php` | Minor fix |
| `app/Http/Middleware/EnsureMFAVerified.php` | Minor fix |
| `app/Http/Requests/StoreInvestmentAccountRequest.php` | Validation update |
| `app/Http/Requests/UpdateInvestmentAccountRequest.php` | Validation update |
| `app/Http/Resources/MortgageResource.php` | Added calculated fields |

### Models (22 files)

| File | Change |
|------|--------|
| `app/Models/CashAccount.php` | Minor |
| `app/Models/Document.php` | Minor |
| `app/Models/Estate/Asset.php` | Minor |
| `app/Models/Estate/Bequest.php` | Minor |
| `app/Models/Estate/Gift.php` | Minor |
| `app/Models/Estate/IHTCalculation.php` | Minor |
| `app/Models/Estate/IHTProfile.php` | Minor |
| `app/Models/Estate/LastingPowerOfAttorney.php` | Minor |
| `app/Models/Estate/Liability.php` | Minor |
| `app/Models/Estate/LpaAttorney.php` | Minor |
| `app/Models/Estate/LpaNotificationPerson.php` | Minor |
| `app/Models/Estate/Trust.php` | Minor |
| `app/Models/Estate/Will.php` | Minor |
| `app/Models/Estate/WillDocument.php` | Minor |
| `app/Models/Investment/InvestmentAccount.php` | Fixed Trust import, scopeIsa, removed scopeActive |
| `app/Models/Investment/RebalancingAction.php` | Added Auditable trait |
| `app/Models/LifeEventAllocation.php` | Added Auditable trait |
| `app/Models/Payment.php` | Added Auditable trait |
| `app/Models/PersonalAccount.php` | Minor |
| `app/Models/RecommendationTracking.php` | Added Auditable trait |
| `app/Models/Subscription.php` | Minor |
| `app/Models/SubscriptionPlan.php` | Added fields |
| `app/Models/User.php` | Minor |

### Services (29 files)

| File | Change |
|------|--------|
| `app/Services/AI/KycGateChecker.php` | Minor |
| `app/Services/AI/Prompts/FcaProcessInstructions.php` | Trimmed duplicate instructions |
| `app/Services/AI/StructuredResponseValidator.php` | Minor |
| `app/Services/AI/SystemPromptBuilder.php` | Joint ownership awareness, family names, prompt cleanup |
| `app/Services/Coordination/CrossModuleStrategyService.php` | TaxDefaults fallback |
| `app/Services/Coordination/HouseholdPlanningService.php` | TaxDefaults fallback |
| `app/Services/Dashboard/DashboardAggregator.php` | Minor |
| `app/Services/Documents/AIExtractionService.php` | Minor |
| `app/Services/Documents/DocumentProcessor.php` | Minor |
| `app/Services/Documents/ExcelParserService.php` | Minor |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | Minor |
| `app/Services/Estate/EstateActionDefinitionService.php` | IHT rate → TaxDefaults::IHT_RATE |
| `app/Services/Estate/FutureValueCalculator.php` | Minor |
| `app/Services/Estate/IHTStrategyGeneratorService.php` | Minor |
| `app/Services/Estate/LpaDocumentService.php` | guessExtension() instead of getClientOriginalExtension() |
| `app/Services/Estate/LpaService.php` | Minor |
| `app/Services/Estate/TrustService.php` | Minor |
| `app/Services/Goals/LifeEventAllocationService.php` | TaxDefaults::ADDITIONAL_RATE_THRESHOLD |
| `app/Services/Investment/ContributionEstimatorService.php` | TaxDefaults::ISA_ALLOWANCE |
| `app/Services/Investment/PortfolioStrategyService.php` | TaxDefaults::ADDITIONAL_RATE_THRESHOLD |
| `app/Services/PrerequisiteGateService.php` | Minor |
| `app/Services/Retirement/AnnualAllowanceChecker.php` | Minor |
| `app/Services/Retirement/PensionPortfolioAnalyzer.php` | Minor |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | TaxDefaults fallbacks |
| `app/Services/Retirement/RetirementProjectionService.php` | Minor |
| `app/Services/Retirement/SalarySacrificeAnalyzer.php` | TaxDefaults::PERSONAL_ALLOWANCE |
| `app/Services/Savings/ISATracker.php` | Minor |
| `app/Services/Savings/SavingsActionDefinitionService.php` | TaxDefaults fallback |
| `app/Services/Tax/IncomeDefinitionsService.php` | Minor |
| `app/Services/Tax/TaxOptimisationService.php` | Minor |
| `app/Services/Tax/TaxProductInfoService.php` | TaxDefaults::ISA_ALLOWANCE |
| `app/Services/UserProfile/UserProfileService.php` | Minor |
| `app/Services/WhatIf/WhatIfScenarioService.php` | Minor |

### Traits

| File | Change |
|------|--------|
| `app/Traits/HasAiChat.php` | Temperature 0.7, cached token logging, legacy code removed |
| `app/Traits/PolicyCRUDTrait.php` | Minor |

### Other Backend

| File | Change |
|------|--------|
| `composer.json` | PHP ^8.2 (was ^8.1) |
| `routes/api.php` | New `/estate/inheritance-tax` route support |

### Seeders (run on server after upload)

| File | Change |
|------|--------|
| `database/seeders/ChrisUserSeeder.php` | Uses env() for password |
| `database/seeders/TaxConfigurationSeeder.php` | Minor |

### Deleted on server

| File | Reason |
|------|--------|
| `app/Services/Estate/GiftingTimelineService.php` | Dead code — functionality in GiftingStrategyOptimizer |

---

## 3. Upload built frontend

Upload `public/build/` directory to `~/www/fynla.org/public_html/public/build/`

**Key frontend changes:**
- Estate dashboard: tab layout → 3-column card grid
- New view: `/estate/inheritance-tax` (IHT calculation detail)
- Cookie consent banner + GA gating
- Registration requires cookie consent
- Hardcoded tax values → taxConfig.js constants
- Design system color fixes (banned tokens removed)
- Dead code removed (investmentService stubs, taxOptimisation store)
- Spouse lifecycle: marital status auto-transitions

---

## 4. SSH and clear caches + reseed

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
php artisan db:seed
```

---

## 5. Post-deploy verification

### Estate Dashboard
1. Select David & Sarah Mitchell preview persona
2. Navigate to Estate Planning
3. Verify: 3-column card grid with 6 cards, no tabs
4. Click IHT Summary card → navigates to `/estate/inheritance-tax`
5. Verify: Full IHT calculation table renders
6. Click "Back to Estate Planning" → returns to card grid
7. Verify: Power of Attorney shows individual LPA types with status
8. Verify: Charitable Bequest shows personalised amount
9. Verify: Life policy shows "Joint Second Death" for married users

### Cookie Consent
10. Open incognito window → navigate to fynla.org
11. Verify: Cookie consent banner appears at bottom
12. Click Decline → warning state appears
13. Click Accept → banner dismisses, GA loads
14. Navigate to /register → form should appear (cookies accepted)

### Tax Values
15. Log in as David Mitchell → Dashboard
16. Scroll to Allowances → ISA shows "of £20,000" (from constant, not hardcoded)
17. Pension shows "of £60,000"

### Spouse Lifecycle
18. As any joint persona → go to Profile → Family
19. Verify: "Spouse" option visible in Add Family Member
20. Delete spouse → prompt with Divorced/Widowed/Keep options

---

## 6. Summary of changes

| Category | Count | Key Items |
|----------|-------|-----------|
| Estate redesign | 5 files | Card grid, IHT detail view, simplified life events |
| Code review fixes | 48 items | Hardcoded tax → TaxDefaults, dead code, security, design system |
| Cookie consent | 4 files | Banner, GA gating, registration gate |
| Spouse lifecycle | 3 files | Marital transitions, sidebar reactivity |
| Bug fixes | 6 files | PA taper, delete cascades, Fyn AI improvements |
| Convention fixes | 51 files | readonly constructors, API resources, Auditable traits |

**Total**: 221 files changed, 2,725 insertions, 2,917 deletions
