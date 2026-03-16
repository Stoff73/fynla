# Deploy: Will Builder Feature

**Date:** 2026-03-16
**Branch:** `estateFix` (navigation fix + will builder integration + seeded data)
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

This creates 1 new table, alters 1 existing, and adds columns:
- **New:** `will_documents` (testator details, executors, guardians, gifts, residuary, funeral, digital assets — all as JSON columns)
- **Altered:** `wills` table gets `will_document_id` FK column
- **Altered:** `will_documents` table gets `signed_date` (date) and `witnesses` (JSON) columns

### 3. Seed
```bash
php artisan db:seed --force
```

---

## Files to Upload

### New PHP Files (7)

```
app/Models/Estate/WillDocument.php
app/Services/Estate/WillDocumentService.php
app/Http/Controllers/Api/Estate/WillDocumentController.php
app/Http/Requests/Estate/SaveWillDocumentRequest.php
database/factories/Estate/WillDocumentFactory.php
database/migrations/2026_03_16_200001_create_will_documents_table.php
database/migrations/2026_03_16_200002_add_signature_and_witness_fields_to_will_documents_table.php
```

### Modified PHP Files (3)

```
app/Models/Estate/Will.php                    — added willDocument() relationship + will_document_id fillable
app/Models/Estate/WillDocument.php            — added signed_date, witnesses to fillable + casts
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

### Modified Frontend Files (8)

```
resources/js/services/estateService.js                                — added 9 will-builder API methods
resources/js/router/index.js                                          — added /estate/will-builder route
resources/js/views/Estate/EstateDashboard.vue                         — conditional "Build Your Will" banner (hidden when will exists)
resources/js/views/Estate/WillBuilderView.vue                         — startAtReview prop for complete documents
resources/js/components/Estate/WillBuilder/WillBuilderWizard.vue      — startAtReview prop, hides progress tracker for complete wills
resources/js/components/Estate/WillBuilder/steps/WillBuilderReviewStep.vue — "Edit Will" (preview-disabled) for complete docs, hides "Complete & Finalise"
resources/js/components/Estate/WillPlanning.vue                       — added "Build Your Will" CTA button
resources/js/utils/willDocumentRenderer.js                            — renders filled signatures + witness details when data present
resources/js/components/SideMenu.vue                                  — "Will" links to /estate/will-builder, isWillBuilderActive computed
resources/js/components/Estate/IHTPlanning.vue                        — Will card navigates to /estate/will-builder
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
- Visit `/estate` — "Build Your Will" banner only shows for users without a will
- Sidebar "Will" → `/estate/will-builder` — complete will shows rendered document (no progress tracker), draft shows wizard
- IHT Planning Will card → `/estate/will-builder`
- David Mitchell: shows signed will with witness details, "Edit Will" button is preview-disabled
- James Carter: shows wizard at step 1 (no will yet), banner visible on estate dashboard
- Margaret Thompson: shows signed simple will with witness details
- Check API: `GET /api/estate/will-builder/pre-populate` returns user data

---

## Summary

| Metric | Count |
|--------|-------|
| New database tables | 1 |
| Altered tables | 1 (wills + will_documents) |
| New API endpoints | 9 |
| New PHP files | 7 |
| Modified PHP files | 3 |
| New Vue components | 13 |
| Modified Vue files | 8 |
| New utility | 1 (willDocumentRenderer.js) |
| New tests | 36 (91 assertions) |
| **Total new/modified files** | **32** |

---

## Seeded Will Documents (5 personas)

| Persona | Will Type | Signed | Witnesses |
|---------|-----------|--------|-----------|
| David Mitchell | Mirror | 20 Mar 2024 | Robert Hartley (Solicitor), Amanda Pearson (Legal Secretary) |
| Sarah Mitchell | Mirror | 20 Mar 2024 | Robert Hartley (Solicitor), Amanda Pearson (Legal Secretary) |
| Margaret Thompson | Simple | 15 Jun 2023 | Dr Helen Cross (GP), Mary Jenkins (Retired Nurse) |
| Patricia Bennett | Mirror | 22 Aug 2023 | Jonathan Adams (Solicitor), Karen Phillips (Legal Executive) |
| Harold Bennett | Mirror | 22 Aug 2023 | Jonathan Adams (Solicitor), Karen Phillips (Legal Executive) |
