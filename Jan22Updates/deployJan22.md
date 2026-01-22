# Deployment - 22 January 2026

## Summary
1. UK postcode lookup feature for all address forms using GetAddress.io API
2. Fix pension projections to include percentage-based contributions (workplace pensions)
3. **CRITICAL SECURITY FIX**: Preview mode session isolation to prevent data leakage
4. Remove Diversification tab from pension detail view

---

## Frontend Rebuild Required: YES

Vue components and JS services were modified.

```bash
./deploy/fynla-org/build.sh
```

---

## Files to Upload

### Backend (PHP)
```
app/Http/Controllers/Api/PostcodeLookupController.php  (NEW)
app/Http/Controllers/Api/PreviewController.php         (SECURITY FIX)
app/Services/Retirement/PensionProjector.php
config/services.php
routes/api.php
```

### Frontend (after rebuild)
```
public/build/  (entire folder)
```

---

## Environment Variable Required

Add to production `.env`:
```
GETADDRESS_API_KEY=UlM5caQqhUSoCCG3sZo1Yw49819
```

---

## SSH Commands After Upload

```bash
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan optimize
```

---

## Files Changed (Reference)

| File | Status | Type |
|------|--------|------|
| `app/Http/Controllers/Api/PostcodeLookupController.php` | NEW | Backend |
| `app/Http/Controllers/Api/PreviewController.php` | SECURITY FIX | Backend |
| `app/Services/Retirement/PensionProjector.php` | Modified | Backend |
| `config/services.php` | Modified | Backend |
| `routes/api.php` | Modified | Backend |
| `resources/js/components/Shared/PostcodeLookup.vue` | NEW | Frontend |
| `resources/js/services/postcodeService.js` | NEW | Frontend |
| `resources/js/components/Estate/AssetForm.vue` | Modified | Frontend |
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Modified | Frontend |
| `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` | Modified | Frontend |
| `resources/js/components/UserProfile/PersonalInformation.vue` | Modified | Frontend |
| `resources/js/components/NetWorth/PensionDetailInline.vue` | Modified | Frontend |

---

## Verification

### Postcode Lookup
1. Go to User Profile → Edit → Enter postcode "SW1A 1AA" → Click "Find Address"
2. Should show dropdown with addresses
3. Select one → Address fields should auto-populate

### Pension Projections
1. Log in as James Carter (young_family persona)
2. Go to Retirement module
3. Verify TechCorp pension shows projected value ~£441,640 (not £0)
4. Log in as David Mitchell (peak_earners persona)
5. Verify Global Finance pension shows projected value ~£716,467
6. Verify SIPP shows projected value ~£1,008,646

### Preview Mode Security (CRITICAL)
1. Log in as a real user (e.g., demo@fps.com)
2. Navigate to landing page (fynla.org)
3. Click "Try the Demo"
4. Select any persona (e.g., Emily & James Carter)
5. **Verify:** Dashboard shows Carter family data (Net Worth £97,200), NOT the real user's data
6. Verify "Preview Mode" banner appears with correct persona name

### Pension Detail View
1. Go to Retirement module → Click on any DC pension
2. **Verify:** Tabs show only: Overview, Projections, Documents
3. **Verify:** No "Diversification" tab appears
