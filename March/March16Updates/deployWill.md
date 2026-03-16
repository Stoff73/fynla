# Deploy: Will Builder Feature

**Date:** 2026-03-16
**Branch:** `will-builder`
**Tests:** 36 new tests, all passing (91 assertions)

---

## Pre-Deployment

### 1. Build locally
```bash
./deploy/fynla-org/build.sh
```

### 2. Run migration on server
```bash
php artisan migrate
```

This creates 1 new table and alters 1 existing:
- **New:** `will_documents` (testator details, executors, guardians, gifts, residuary, funeral, digital assets — all as JSON columns)
- **Altered:** `wills` table gets `will_document_id` FK column

### 3. Seed
```bash
php artisan db:seed --force
```

---

## Files to Upload

### New PHP Files (6)

```
app/Models/Estate/WillDocument.php
app/Services/Estate/WillDocumentService.php
app/Http/Controllers/Api/Estate/WillDocumentController.php
app/Http/Requests/Estate/SaveWillDocumentRequest.php
database/factories/Estate/WillDocumentFactory.php
database/migrations/2026_03_16_200001_create_will_documents_table.php
```

### Modified PHP Files (2)

```
app/Models/Estate/Will.php                    — added willDocument() relationship + will_document_id fillable
routes/api.php                                — added 9 will-builder routes in estate group
```

### New Frontend Files (13)

```
resources/js/views/Estate/WillBuilderView.vue
resources/js/components/Estate/WillBuilder/WillBuilderWizard.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderIntroStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderPersonalStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderExecutorsStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderGuardiansStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderGiftsStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderResiduaryStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderFuneralStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderDigitalStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderReviewStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderSigningStep.vue
resources/js/utils/willDocumentRenderer.js
```

### Modified Frontend Files (4)

```
resources/js/services/estateService.js        — added 9 will-builder API methods
resources/js/router/index.js                  — added /estate/will-builder route
resources/js/views/Estate/EstateDashboard.vue — added "Build Your Will" banner card
resources/js/components/Estate/WillPlanning.vue — added "Build Your Will" CTA button
```

### Test Files (not deployed, for reference)

```
tests/Unit/Services/Estate/WillDocumentServiceTest.php    — 22 unit tests
tests/Feature/Estate/WillBuilderApiTest.php               — 14 feature tests
database/factories/Estate/WillDocumentFactory.php          — test factory
```

---

## Post-Deployment

### 1. Clear caches
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 2. Verify
- Visit `/estate` — "Build Your Will" banner should appear
- Click through to `/estate/will-builder` — wizard should load with 10 steps
- Check API: `GET /api/estate/will-builder/pre-populate` returns user data

---

## Summary

| Metric | Count |
|--------|-------|
| New database tables | 1 |
| Altered tables | 1 |
| New API endpoints | 9 |
| New PHP files | 6 |
| Modified PHP files | 2 |
| New Vue components | 13 |
| Modified Vue files | 4 |
| New utility | 1 (willDocumentRenderer.js) |
| New tests | 36 (91 assertions) |
| **Total new/modified files** | **26** |
