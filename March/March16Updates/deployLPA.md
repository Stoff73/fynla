# Deploy: Lasting Power of Attorney Feature

**Date:** 2026-03-16
**Branch:** `will-builder` (includes LPA feature)
**Version:** v0.9.0 → v0.9.2
**Tests:** 38 new LPA tests, all passing (93 assertions)

---

## Pre-Deployment

### 1. Build locally
```bash
./deploy/fynla-org/build.sh
```

### 2. Run migrations on server
```bash
php artisan migrate
```

This creates 3 new tables:
- `lasting_powers_of_attorney`
- `lpa_attorneys`
- `lpa_notification_persons`

### 3. Seed preview data
```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

### 4. Clear caches
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Files to Upload

### New PHP Files (14 files)

**Models (3)**
```
app/Models/Estate/LastingPowerOfAttorney.php
app/Models/Estate/LpaAttorney.php
app/Models/Estate/LpaNotificationPerson.php
```

**Services (3)**
```
app/Services/Estate/LpaService.php
app/Services/Estate/LpaComplianceService.php
app/Services/Estate/LpaDocumentService.php
```

**Controller (1)**
```
app/Http/Controllers/Api/Estate/LpaController.php
```

**Form Requests (3)**
```
app/Http/Requests/Estate/StoreLpaRequest.php
app/Http/Requests/Estate/UpdateLpaRequest.php
app/Http/Requests/Estate/UploadLpaRequest.php
```

**Migrations (3)**
```
database/migrations/2026_03_16_100001_create_lasting_powers_of_attorney_table.php
database/migrations/2026_03_16_100002_create_lpa_attorneys_table.php
database/migrations/2026_03_16_100003_create_lpa_notification_persons_table.php
```

**Factories (3) — development/staging only**
```
database/factories/Estate/LastingPowerOfAttorneyFactory.php
database/factories/Estate/LpaAttorneyFactory.php
database/factories/Estate/LpaNotificationPersonFactory.php
```

### Modified PHP Files (5 files)

```
app/Models/User.php                                    — added lastingPowersOfAttorney() relationship
app/Models/Document.php                                — added TYPE_LPA constant
app/Services/Estate/EstateDataReadinessService.php     — checkPowerOfAttorney() now uses LPA model, upgraded to warning level
routes/api.php                                         — added LpaController import + 9 LPA routes in estate group
database/seeders/PreviewUserSeeder.php                 — added createLpas() for peak_earners, widow, retired_couple
```

### New Frontend Files (16 files)

**Views (1)**
```
resources/js/views/Estate/LpaWizardView.vue
```

**Components — Tab & Display (6)**
```
resources/js/components/Estate/PowerOfAttorneyTab.vue
resources/js/components/Estate/LpaSummaryCard.vue
resources/js/components/Estate/LpaDetailView.vue
resources/js/components/Estate/LpaComplianceChecklist.vue
resources/js/components/Estate/LpaUploadForm.vue
resources/js/components/Estate/AddressFieldGroup.vue
```

**Components — Wizard (10)**
```
resources/js/components/Estate/LpaWizard.vue
resources/js/components/Estate/LpaWizardSteps/DonorDetailsStep.vue
resources/js/components/Estate/LpaWizardSteps/AttorneysStep.vue
resources/js/components/Estate/LpaWizardSteps/ReplacementAttorneysStep.vue
resources/js/components/Estate/LpaWizardSteps/DecisionTypeStep.vue
resources/js/components/Estate/LpaWizardSteps/WhenCanActStep.vue
resources/js/components/Estate/LpaWizardSteps/PreferencesStep.vue
resources/js/components/Estate/LpaWizardSteps/CertificateProviderStep.vue
resources/js/components/Estate/LpaWizardSteps/NotificationPersonsStep.vue
resources/js/components/Estate/LpaWizardSteps/ReviewStep.vue
```

### Modified Frontend Files (7 files)

```
resources/js/views/Estate/EstateDashboard.vue          — added 5th tab (Power of Attorney), reads tab query param on mount
resources/js/services/estateService.js                 — added 9 LPA API methods
resources/js/store/modules/estate.js                   — added LPA state, getters, mutations, actions
resources/js/router/index.js                           — added /estate/lpa/create/:type route
resources/js/components/SideMenu.vue                   — added Power of Attorney nav item in Family section with isLpaActive computed
resources/js/components/SideMenuIcon.vue               — added 'key' icon for Power of Attorney
resources/js/views/Version.vue                         — updated current version to v0.9.2, moved v0.9.0 to previous versions
```

### Built Assets (uploaded via build script)

```
public/build/                                          — entire directory (built by ./deploy/fynla-org/build.sh)
```

---

## New API Endpoints (9)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/estate/lpa` | List all LPAs for user |
| POST | `/api/estate/lpa` | Create new LPA |
| GET | `/api/estate/lpa/donor-defaults` | Auto-fill donor from profile |
| POST | `/api/estate/lpa/upload` | Upload existing LPA document |
| GET | `/api/estate/lpa/{id}` | Get single LPA with relations |
| PUT | `/api/estate/lpa/{id}` | Update LPA |
| DELETE | `/api/estate/lpa/{id}` | Soft-delete LPA |
| GET | `/api/estate/lpa/{id}/compliance` | Run compliance checks |
| POST | `/api/estate/lpa/{id}/register` | Mark as registered with the Office of the Public Guardian |

All endpoints are protected by `auth:sanctum` and `PreviewWriteInterceptor`.

---

## New Database Tables (3)

### lasting_powers_of_attorney
Main LPA record — donor details, attorney decisions, certificate provider, registration status, document link. Supports soft deletes. Indexed on `(user_id, lpa_type)` and `(user_id, status)`.

### lpa_attorneys
Primary and replacement attorneys — name, DOB, address, relationship. Indexed on `(lasting_power_of_attorney_id, attorney_type)`.

### lpa_notification_persons
Up to 5 people to notify during Office of the Public Guardian registration — name and address. Indexed on `lasting_power_of_attorney_id`.

---

## Preview Persona Data

| Persona | LPA Data |
|---------|----------|
| peak_earners (David Mitchell) | Both types registered, 2 primary + 1 replacement attorney, jointly and severally |
| widow (Margaret Thompson) | Property registered, Health in draft, 2 primary attorneys acting jointly |
| retired_couple (Patricia & Harold Bennett) | Both types registered for both spouses, replacement attorneys, notification persons |

---

## Post-Deployment Verification

1. Navigate to Estate Planning → Power of Attorney tab appears as 5th tab
2. Both LPA type cards render (Property & Financial / Health & Welfare)
3. Click "Create" → wizard loads with donor details pre-filled
4. Complete wizard → LPA saves and appears on tab
5. Click "View Details" → full OPG-format display with compliance checks
6. Click "Print / Save as PDF" → browser print dialog opens
7. Log in as peak_earners → both registered LPAs display
8. Log in as widow → Property registered, Health in draft
9. Log in as retired_couple → both spouses have both types registered

---

## Rollback

If needed, the 3 new tables can be dropped without affecting existing data:
```sql
DROP TABLE IF EXISTS lpa_notification_persons;
DROP TABLE IF EXISTS lpa_attorneys;
DROP TABLE IF EXISTS lasting_powers_of_attorney;
```

Then remove the LPA routes from `routes/api.php` and revert the modified files. Frontend will gracefully handle the missing tab (the Power of Attorney tab simply won't load data).

---

## File Count Summary

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| PHP (backend) | 14 | 5 | 19 |
| Vue/JS (frontend) | 16 | 7 | 23 |
| **Total** | **30** | **12** | **42** |
