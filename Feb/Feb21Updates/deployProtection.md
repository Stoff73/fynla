# Protection Module Deployment Notes

## Branch: protection
## Date: 2026-02-21
## Status: DEPLOYED 21 February 2026

## Summary
8 fixes from protection module audit: real coverage gap calculations and adequacy scores, Auditable trait consistency, BasePolicyRequest refactor, API Resources for all policy types, disability/sickness policy seeding, EstateAgent non-trust cover surfacing, RecommendationEngine collection fix, score colour fix, and Dashboard/ProtectionOverviewCard integration for all 5 policy types.

## Files Changed

### New Files (6 API Resources)
- `app/Http/Resources/Protection/LifeInsurancePolicyResource.php`
- `app/Http/Resources/Protection/CriticalIllnessPolicyResource.php`
- `app/Http/Resources/Protection/IncomeProtectionPolicyResource.php`
- `app/Http/Resources/Protection/DisabilityPolicyResource.php`
- `app/Http/Resources/Protection/SicknessIllnessPolicyResource.php`
- `app/Http/Resources/Protection/ProtectionProfileResource.php`

### Services (3 files)
- `app/Services/Protection/CoverageGapAnalyzer.php` - Real income-based gap calculations for IP, disability, sickness/illness
- `app/Services/Protection/AdequacyScorer.php` - Real CI score (3x income benchmark) and IP score (60% income benchmark); score colour orange -> blue
- `app/Services/Protection/RecommendationEngine.php` - Fixed empty() on Eloquent collection -> isEmpty()

### Agents (2 files)
- `app/Agents/ProtectionAgent.php` - Augments needs with CI coverage for score calculation
- `app/Agents/EstateAgent.php` - Queries non-trust life policies, surfaces total_cover_not_in_trust, trust placement recommendation

### Controller & Trait (2 files)
- `app/Http/Controllers/Api/ProtectionController.php` - Returns API Resources instead of raw models
- `app/Traits/PolicyCRUDTrait.php` - Accepts optional resource class parameter

### Request Validation (10 files)
- `app/Http/Requests/Protection/StoreLifePolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/StoreCriticalIllnessPolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/StoreDisabilityPolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/StoreSicknessIllnessPolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/UpdateLifePolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/UpdateCriticalIllnessPolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/UpdateIncomeProtectionPolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/UpdateDisabilityPolicyRequest.php` - Extends BasePolicyRequest
- `app/Http/Requests/Protection/UpdateSicknessIllnessPolicyRequest.php` - Extends BasePolicyRequest

### Models (2 files)
- `app/Models/DisabilityPolicy.php` - Added Auditable trait
- `app/Models/SicknessIllnessPolicy.php` - Added Auditable trait

### Seeders (1 file)
- `database/seeders/PreviewUserSeeder.php` - Added createDisabilityPolicies() and createSicknessIllnessPolicies() methods; cleanup in deleteUserData()

### Frontend (3 files)
- `resources/js/views/Dashboard.vue` - Added disability and sickness/illness policy sections to protection card; fixed hasProtectionData
- `resources/js/components/Protection/ProtectionOverviewCard.vue` - Added sickness/illness template section; fixed provider field name; improved disability details
- `resources/js/data/personas/peak_earners.json` - David: employer accident & sickness cover; Sarah: NHS PHI
- `resources/js/data/personas/entrepreneur.json` - Alex: personal accident cover

### Tests (1 file)
- `tests/Unit/Services/Protection/AdequacyScorerTest.php` - Updated score colour expectations orange -> blue

## Post-Deploy Steps
```bash
php artisan cache:clear && php artisan route:clear && php artisan config:clear
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

## No Migrations Required
