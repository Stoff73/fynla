# Complete Deployment Guide — March 13–16, 2026

**Version:** v0.8.3 → v0.9.2
**Covers:** All changes from 13 March to 16 March 2026
**PRs merged:** #122–#133 (12 pull requests)
**Status:** DEPLOYED TO PRODUCTION — 16 March 2026
**Total files to upload:** 213 PHP + frontend files (excluding tests)

**CRITICAL: This is a cumulative deploy. Every file listed below must be uploaded.**

---

## Pre-Deployment Checklist

### 1. Build locally
```bash
./deploy/fynla-org/build.sh
```

### 2. Run migrations on server
```bash
php artisan migrate
```

This creates **4 new tables** and **alters 2 existing**:

| Migration | Action |
|-----------|--------|
| `2026_03_16_100001_create_lasting_powers_of_attorney_table` | New table: LPA records |
| `2026_03_16_100002_create_lpa_attorneys_table` | New table: LPA attorneys |
| `2026_03_16_100003_create_lpa_notification_persons_table` | New table: notification persons |
| `2026_03_16_200001_create_will_documents_table` | New table: will documents + adds `will_document_id` FK to `wills` |
| `2026_03_16_200002_add_signature_and_witness_fields_to_will_documents_table` | Adds `signed_date`, `witnesses` to `will_documents` |
| `2026_03_16_300001_add_rate_valid_until_to_savings_accounts_table` | Adds `rate_valid_until` to `savings_accounts` |

### 3. Seed preview data
```bash
php artisan db:seed --force
```

### 4. Clear caches
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Files to Upload

### Built Assets (always upload first)

```
public/build/                                              — entire directory
```

---

### New PHP Files (21)

**Models (4)**
```
app/Models/Estate/LastingPowerOfAttorney.php
app/Models/Estate/LpaAttorney.php
app/Models/Estate/LpaNotificationPerson.php
app/Models/Estate/WillDocument.php
```

**Services (4)**
```
app/Services/Estate/LpaService.php
app/Services/Estate/LpaComplianceService.php
app/Services/Estate/LpaDocumentService.php
app/Services/Estate/WillDocumentService.php
```

**Controllers (2)**
```
app/Http/Controllers/Api/Estate/LpaController.php
app/Http/Controllers/Api/Estate/WillDocumentController.php
```

**Form Requests (4)**
```
app/Http/Requests/Estate/StoreLpaRequest.php
app/Http/Requests/Estate/UpdateLpaRequest.php
app/Http/Requests/Estate/UploadLpaRequest.php
app/Http/Requests/Estate/SaveWillDocumentRequest.php
```

**Migrations (6)**
```
database/migrations/2026_03_16_100001_create_lasting_powers_of_attorney_table.php
database/migrations/2026_03_16_100002_create_lpa_attorneys_table.php
database/migrations/2026_03_16_100003_create_lpa_notification_persons_table.php
database/migrations/2026_03_16_200001_create_will_documents_table.php
database/migrations/2026_03_16_200002_add_signature_and_witness_fields_to_will_documents_table.php
database/migrations/2026_03_16_300001_add_rate_valid_until_to_savings_accounts_table.php
```

**Factories (3) — development/staging only**
```
database/factories/Estate/LastingPowerOfAttorneyFactory.php
database/factories/Estate/LpaAttorneyFactory.php
database/factories/Estate/LpaNotificationPersonFactory.php
database/factories/Estate/WillDocumentFactory.php
```

---

### Modified PHP Files (14)

```
app/Agents/EstateAgent.php                                 — updated for will document integration
app/Http/Controllers/Api/Plans/PlanController.php          — added SavingsPlanService to constructor, getPlanService(), statuses()
app/Models/Document.php                                    — added TYPE_LPA constant
app/Models/Estate/Will.php                                 — added willDocument() relationship + will_document_id fillable
app/Models/SavingsAccount.php                              — added rate_valid_until to fillable + casts
app/Models/User.php                                        — added lastingPowersOfAttorney() relationship
app/Services/Dashboard/DashboardAggregator.php             — wired all 5 agents with real user-specific data (was hardcoded stubs)
app/Services/Estate/EstateDataReadinessService.php         — checkPowerOfAttorney() uses LPA model, upgraded to warning
app/Services/Retirement/RetirementDataReadinessService.php — income check downgraded to warning for already-retired users
app/Services/Retirement/RetirementActionDefinitionService.php — updated action definitions
app/Services/Retirement/SalarySacrificeAnalyzer.php        — updated analysis
app/Services/Savings/SavingsActionDefinitionService.php    — updated action definitions
database/seeders/PreviewUserSeeder.php                     — added createLpas(), createWillDocuments(), LPA cleanup in deleteUserData()
database/seeders/TaxConfigurationSeeder.php                — updated tax config
routes/api.php                                             — added LpaController + WillDocumentController routes (18 new endpoints)
```

---

### New Frontend Files (30)

**Views (3)**
```
resources/js/views/Estate/LpaWizardView.vue
resources/js/views/Estate/PowerOfAttorneyView.vue
resources/js/views/Estate/WillBuilderView.vue
```

**Components — LPA Tab & Display (6)**
```
resources/js/components/Estate/PowerOfAttorneyTab.vue
resources/js/components/Estate/LpaSummaryCard.vue
resources/js/components/Estate/LpaDetailView.vue
resources/js/components/Estate/LpaComplianceChecklist.vue
resources/js/components/Estate/LpaUploadForm.vue
resources/js/components/Estate/AddressFieldGroup.vue
```

**Components — LPA Wizard (9)**
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

**Components — Will Builder (10)**
```
resources/js/components/Estate/WillBuilder/WillBuilderWizard.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderIntroStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderPersonalStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderExecutorsStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderGuardiansStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderGiftsStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderResiduaryStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderFuneralStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderDigitalStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderSigningStep.vue
```

**Utilities (2)**
```
resources/js/utils/willDocumentRenderer.js
resources/js/utils/lpaDocumentRenderer.js
```

---

### Modified Frontend Files (15)

```
resources/js/components/Dashboard/AreasToCompleteCard.vue   — will link → /estate/will-builder
resources/js/components/Dashboard/AreasToConsiderCard.vue   — will link → /estate/will-builder
resources/js/components/Estate/IHTPlanning.vue              — added Power of Attorney card, Will card → will-builder, LPA data on mount
resources/js/components/Estate/WillPlanning.vue             — added "Build Your Will" CTA
resources/js/components/Navbar.vue                          — removed Will link from dropdown
resources/js/components/Shared/AiChatPanel.vue              — Fyn streaming + stop button
resources/js/components/SideMenu.vue                        — Will → /estate/will-builder, LPA → /estate/power-of-attorney, active states
resources/js/components/SideMenuIcon.vue                    — added 'key' icon for Power of Attorney
resources/js/router/index.js                                — added /estate/will-builder, /estate/power-of-attorney, /estate/lpa/create/:type + preview routes
resources/js/services/estateService.js                      — added 9 LPA + 9 will-builder API methods
resources/js/store/modules/aiChat.js                        — streaming support
resources/js/store/modules/estate.js                        — added LPA state, getters, mutations, actions
resources/js/views/Estate/EstateDashboard.vue               — removed LPA tab, conditional will banner
resources/js/views/ValuableInfo.vue                         — removed Will tab
resources/js/views/Version.vue                              — updated to v0.9.2
```

---

### Fyn Assistant Files (5)

```
app/Services/AI/AiChatService.php                          — streaming, prompt caching, model tiering
app/Services/AI/AiContextBuilder.php                       — richer financial context
app/Services/AI/AiModelResolver.php                        — model tiering for Pro users
app/Services/AI/AiToolDefinitions.php                      — strict schema validation, 17 tools
app/Services/AI/AiToolExecutor.php                         — input validation, duplicate detection
```

---

## New API Endpoints (18)

### Lasting Power of Attorney (9)

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
| POST | `/api/estate/lpa/{id}/register` | Mark as registered with OPG |

### Will Builder (9)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/estate/will-builder` | Get existing will document (draft or complete) |
| POST | `/api/estate/will-builder` | Create new will document |
| PUT | `/api/estate/will-builder/{id}` | Update will document |
| GET | `/api/estate/will-builder/{id}` | Get specific will document |
| GET | `/api/estate/will-builder/pre-populate` | Auto-fill from user profile |
| POST | `/api/estate/will-builder/{id}/complete` | Mark as complete |
| POST | `/api/estate/will-builder/{id}/mirror` | Generate spouse's mirror will |
| GET | `/api/estate/will-builder/{id}/validate` | Run validation checks |
| DELETE | `/api/estate/will-builder/{id}` | Delete will document |

All endpoints protected by `auth:sanctum` and `PreviewWriteInterceptor`.

---

## New Database Tables (4)

| Table | Purpose |
|-------|---------|
| `lasting_powers_of_attorney` | LPA records — donor, attorneys, certificate provider, registration |
| `lpa_attorneys` | Primary and replacement attorneys |
| `lpa_notification_persons` | People to notify during OPG registration |
| `will_documents` | Will Builder documents — testator, executors, guardians, gifts, residuary, funeral, digital assets, witnesses |

---

## Seeded Preview Data

### Lasting Power of Attorney

| Persona | LPA Data |
|---------|----------|
| David Mitchell | Property & Financial (LP-2024-0847291) + Health & Welfare (LP-2024-0847292), registered, 2 attorneys (Sarah + brother), preferences + instructions |
| Sarah Mitchell | Property & Financial (LP-2024-0953104) + Health & Welfare (LP-2024-0953105), registered, 2 attorneys (David + sister) |
| Margaret Thompson | Property & Financial registered (LP-2023-0612845), Health & Welfare draft, primary + replacement attorney |

### Will Documents

| Persona | Will Type | Signed | Witnesses |
|---------|-----------|--------|-----------|
| David Mitchell | Mirror | 20 Mar 2024 | Robert Hartley (Solicitor), Amanda Pearson (Legal Secretary) |
| Sarah Mitchell | Mirror | 20 Mar 2024 | Robert Hartley (Solicitor), Amanda Pearson (Legal Secretary) |
| Margaret Thompson | Simple | 15 Jun 2023 | Dr Helen Cross (GP), Mary Jenkins (Retired Nurse) |
| Patricia Bennett | Mirror | 22 Aug 2023 | Jonathan Adams (Solicitor), Karen Phillips (Legal Executive) |
| Harold Bennett | Mirror | 22 Aug 2023 | Jonathan Adams (Solicitor), Karen Phillips (Legal Executive) |

---

## Post-Deployment Verification

### Estate Planning
1. **Sidebar "Estate Planning"** → `/estate` → IHT cards (Will, Gifting, Life Policy, Charitable Bequest, Power of Attorney)
2. **Sidebar "Power of Attorney"** → `/estate/power-of-attorney` → standalone LPA page with summary cards
3. **Sidebar "Will"** → `/estate/will-builder` → complete will at Review step (for personas with wills)
4. **IHT Planning Power of Attorney card** → `/estate/power-of-attorney`
5. **IHT Planning Will card** → `/estate/will-builder`
6. **Will Builder banner** → hidden for David Mitchell (has will), shown for James Carter (no will)

### Will Builder
7. David Mitchell: signed will with witness details, "Edit Will" button (preview-disabled), no progress tracker
8. Margaret Thompson: signed simple will, burial wishes
9. James Carter: wizard at step 1 (no will)
10. Navbar dropdown: no Will link
11. Valuable Info page: no Will tab (Letter, Income, Expenditure, Risk Profile only)

### Lasting Power of Attorney
12. David Mitchell: 4 registered LPAs (2 David, 2 Sarah) in legal OPG format with signatures
13. Margaret Thompson: 1 registered + 1 draft, legal format with signatures
14. Click "View Details" → legal document with donor, attorney, certificate provider signatures + registration stamp
15. LPA wizard: `/estate/lpa/create/property_financial` still works
16. `detail-inline-back` button at top of LPA detail view

### Plans & Dashboard
17. `/api/plans/savings` → returns data (was 404)
18. Plan statuses include `savings` key
19. Dashboard cards show real user-specific values (not identical stubs)
20. David Mitchell dashboard: savings £102k, investments £220k, retirement £46k projected, estate £1.46M

### Retirement
21. Patricia Bennett retirement: projected income £18,500 (DB) + £11,500 (state after SPA) = £30,000
22. Income gap: £5,000 vs target £35,000

### Savings Alerts
23. `php artisan savings:send-alerts` runs without column error

### Fyn Assistant
24. Fyn responses stream word-by-word
25. "Stop generating" button works mid-stream
26. Prompt caching active (check response headers)

---

## Rollback

### LPA Tables
```sql
DROP TABLE IF EXISTS lpa_notification_persons;
DROP TABLE IF EXISTS lpa_attorneys;
DROP TABLE IF EXISTS lasting_powers_of_attorney;
```

### Will Documents
```sql
ALTER TABLE wills DROP FOREIGN KEY wills_will_document_id_foreign;
ALTER TABLE wills DROP COLUMN will_document_id;
DROP TABLE IF EXISTS will_documents;
```

### rate_valid_until
```sql
ALTER TABLE savings_accounts DROP COLUMN rate_valid_until;
```

Remove corresponding routes from `routes/api.php` and revert modified files.

---

## File Count Summary

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| PHP (backend) | 21 | 14 | 35 |
| Vue/JS (frontend) | 30 | 15 | 45 |
| Fyn Assistant | 0 | 5 | 5 |
| Migrations | 6 | 0 | 6 |
| Tests (not deployed) | 4 | 1 | 5 |
| **Total (deployable)** | **51** | **34** | **85** |
