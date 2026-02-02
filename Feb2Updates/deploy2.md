# Deployment Notes - February 2, 2026

---

## 1. Retirement Income Planner - Agentic AI Development Note

**Status:** ✅ DEPLOYED

### Description

Added an informational banner to the Retirement Income Planner explaining that the current implementation is scaffolding for future Agentic AI integration. This sets user expectations that the symbolic AI implementation may produce imperfect results until the LLM-based agent is connected.

### Changes

Added a blue info banner at the top of the Retirement Income Planner with the following message:

> The retirement planner you see here is the base scaffolding that will be used by our Agentic AI (not implemented yet) running on a domain-specific, deep knowledge LLM, to provide actionable, deterministic and traceable optimisation strategies for drawdown. So if what you see is not perfect, or does not make sense, this is expected behaviour due to the nature of symbolic AI (which is implemented). Once we connect the agent, the AI will adjust the parameters accordingly.

### Files Changed

```text
resources/js/components/Retirement/RetirementIncomeTab.vue
```

---

## 2. Persona Selection Modal - Register Button Message Update

**Status:** ✅ DEPLOYED

### Description

Updated the message in the persona selection modal (shown when users click "Get Started", "Interactive Demo", or "Try Demo") to encourage users to explore personas before registering.

### Changes

Updated the register section message from:
> "We strongly encourage you to explore the personas above first to see what Fynla can do."

To:
> "We strongly recommend looking through a persona to see the full power of the platform."

### Files Changed

```text
resources/js/components/Preview/PersonaSelectionModal.vue
```

---

## 3. Capital Adequacy Tab - New Feature

**Status:** ✅ DEPLOYED

### Description

Created a new Capital Adequacy Tab accessible from the Capital Adequacy Planner card on the Retirement dashboard. This provides users with a comprehensive view of their pension allowance usage, carry forward availability, and a what-if contribution slider.

### Features

1. **Summary Cards (4 cards)**
   - Required Capital at Retirement
   - Projected Capital at Retirement (color-coded green/red based on status)
   - Annual Allowance Used (current tax year)
   - Carry Forward Available (breakdown by last 3 tax years with total)

2. **Annual Allowance Progress Section**
   - Progress bar showing allowance used vs remaining
   - Breakdown: Remaining Allowance + Carry Forward = Total Available
   - Monthly equivalent display
   - Affordability limitation note (when applicable)

3. **What-If Contribution Slider**
   - Current monthly contribution display
   - Interactive slider to model additional contributions
   - Constrained by minimum of affordability OR remaining allowance
   - Shows which constraint is limiting (affordability or allowance)
   - Impact panel showing:
     - New annual contribution
     - Additional capital at retirement (compound growth calculation)
     - New projected capital
     - Capital gap/surplus

4. **Capital Progress Section**
   - Progress bar showing projected vs required capital
   - Status banner (On Track / Nearly There / Capital Shortfall)

### Technical Details

- Contributions calculation includes both percentage-based (occupational pensions) and flat monthly amounts
- Carry forward assumes same contribution level for previous 3 tax years
- Compound growth calculation matches backend RequiredCapitalCalculator formula
- What-if slider is visualization only (no persistence)

### Files Changed

```text
resources/js/components/Retirement/CapitalAdequacyTab.vue  (NEW)
resources/js/components/NetWorth/PensionList.vue
```

---

## 4. Letter to Spouse - UI Improvements

**Status:** ✅ DEPLOYED

### Description

Comprehensive UI improvements to the Letter to Spouse component, including executor details integration from Will data, improved formatting, and design system compliance.

### Changes

1. **Executor Details from Will**
   - Auto-populates executor name from Will data via `/estate/will` API
   - Shows "From Will" badge when executor info comes from Will
   - Displays executor notes if available

2. **Immediate Actions Checklist**
   - Parsed text into numbered action items
   - Two-column layout for better readability
   - Styled number badges for each action

3. **Financial Overview - Pensions Added**
   - New Pensions section fetching DC and DB pensions from `/retirement` API
   - Displays pension type badges (DC/DB)
   - Shows scheme name, provider, value, and employer

4. **Card Styling Updates**
   - All cards changed from grey (`bg-gray-50`) to white (`bg-white`)
   - Inline flex layout for savings, pensions, and properties cards
   - Consistent border and padding styling

5. **Key Contacts Section**
   - Icons added for each contact type
   - All contact cards have consistent white background with grey border
   - Executor shows "From Will" badge when data comes from Will

6. **Additional Information Boxes**
   - Users can add custom information boxes in Part 3
   - Up to 10 additional boxes allowed
   - Each box has title and content fields
   - Remove button for each box
   - Counter shows current/max boxes (e.g., "3/10")
   - Boxes persist with letter data

7. **Bequests & Legacies Section**
   - New section in Part 2: Financial Overview displaying bequests from Will data
   - Shows beneficiary name, bequest type (Share/Legacy/Asset), and value/description
   - Colour-coded badges for bequest types (blue=percentage, green=amount, purple=asset)
   - Displays conditions if specified
   - "From Will" badge indicates data sourced from estate planning

8. **Print / Save PDF Feature**
   - New "Print / Save PDF" button visible next to Edit button (always visible when not editing)
   - Opens a new browser window with clean, professionally formatted letter content
   - No browser headers/footers (date, URL, page title) - uses `@page { margin: 0 }` technique
   - Fynla logo (120px) displayed in top right corner
   - PDF includes all four parts: Immediate Actions, Financial Overview, Additional Info, Final Wishes
   - Includes bequests section from Will data
   - Proper page spacing: first page starts at top, subsequent pages have 12mm top margin
   - All cards have white backgrounds with consistent grey borders
   - Print window auto-closes after printing/saving
   - Works in preview mode for testing

### Files Changed

```text
resources/js/components/UserProfile/LetterToSpouse.vue
```

---

## 5. Preview Persona Letter to Spouse Data

**Status:** ✅ DEPLOYED

### Description

Added comprehensive Letter to Spouse/Expression of Wishes data to preview personas so users can see fully populated examples. Includes professional contacts, valuable items, funeral wishes, and additional information boxes.

### Personas Updated

1. **Mitchells (peak_earners)** - David & Sarah Mitchell
   - Financial adviser: James Patterson at Hargreaves Lansdown
   - Solicitor: Henderson & Co Solicitors
   - Accountant: Graham & Associates
   - Both have funeral preferences and personal messages
   - Additional boxes: Manchester BTL details, Education Trust, NHS pension info
   - **Will & Bequests**: Children 50% each (held in trust until 25), Cancer Research UK/British Heart Foundation £10k legacies

2. **Alex Chen (entrepreneur)** - Single user (Expression of Wishes)
   - Financial adviser: Interactive Investor Advisory Team
   - Solicitor: Pannone Corporate LLP
   - Accountant: BDO Manchester
   - Additional boxes: Business succession, TechAngel Ventures, Director's loan, Parents care
   - **Will & Bequests**: Parents (40% each), Manchester Tech Foundation (10%), Cancer Research UK (£25k), Business partner first refusal on shares

3. **Margaret Thompson (widow)** - Widowed user (Expression of Wishes)
   - Financial adviser: Helen Richards at Hargreaves Lansdown
   - **Will & Bequests**: Children Andrew (40%) & Catherine (40%), Grandchildren Education Trust (15%), Cotswold Care Hospice £25k, St Lawrence Church £5k, Rose Cottage to Catherine
   - Solicitor: Smithson Solicitors LLP
   - Additional boxes: Discretionary trust, Padstow cottage, Offshore bond, Education planning, IHT notes

4. **Bennetts (retired_couple)** - Patricia & Harold Bennett
   - Solicitor: Adams & Co Solicitors
   - Self-directed investments (no adviser)
   - Both have funeral preferences and personal messages
   - Additional boxes: NHS pension, Civil Service pension, JISAs, IHT planning, Care costs
   - **Will & Bequests**: Children Mark & Susan (50% each on second death), Grandchildren Education Fund £25k

### Technical Changes

1. **Migration**: Added `additional_boxes` JSON column to `letters_to_spouse` table
2. **Model**: Added `additional_boxes` to fillable and casts as array
3. **Seeder**: Added `createLetterToSpouse()` method with chattel-to-valuable-items generation
4. **JSON Files**: Added `letter_to_spouse` objects to 4 persona files

### Files Changed

```text
database/migrations/2026_02_02_095622_add_additional_boxes_to_letters_to_spouse_table.php (NEW)
app/Models/LetterToSpouse.php
database/seeders/PreviewUserSeeder.php
resources/js/data/personas/peak_earners.json
resources/js/data/personas/entrepreneur.json
resources/js/data/personas/widow.json
resources/js/data/personas/retired_couple.json
```

---

## 6. Document Upload - PDF Text Extraction & Cleanup

**Status:** ✅ DEPLOYED

### Description

Major improvements to the document upload feature:
- Switched AI model to Claude 3.5 Haiku for faster, cheaper extraction
- Added PDF text extraction using `smalot/pdfparser` to avoid large base64 payloads
- Implemented noise filtering to remove T&Cs, legal disclaimers, and marketing content
- Removed Excel/CSV support (PDF and images only)
- Standardised 20MB file size limit

### Changes

1. **Model Update**
   - Changed from `claude-sonnet-4-5` to `claude-3-5-haiku-20241022`
   - Faster extraction with lower API costs

2. **PDF Text Extraction** (NEW)
   - Uses `smalot/pdfparser` to extract text from PDFs
   - Sends text content (~50KB) instead of base64 images (~40MB) to Claude API
   - Falls back to image-based extraction for scanned PDFs (no extractable text)
   - Scanned PDFs limited to 15MB (with helpful error message and suggestions)

3. **Noise Filtering** (NEW)
   - Filters out T&Cs, legal disclaimers, regulatory info, marketing content
   - Preserves financial data (fund names, costs, values, contributions, dates)
   - Removes page numbers, headers, footers, promotional content
   - Significantly reduces payload size while retaining important information

4. **Removed Excel/CSV Support**
   - Supported formats now: PDF, PNG, JPG, WebP only
   - Simplifies the upload flow and reduces complexity
   - Removed ExcelParserService dependency from AIExtractionService

5. **Standardised 20MB Limit**
   - All file types: 20MB maximum
   - Scanned PDFs (no extractable text): 15MB limit with helpful error message
   - Frontend image compression still applies for images > 2MB

6. **Storage Fix**
   - Changed from S3 to local disk storage (S3 adapter not installed on server)
   - Documents stored in `storage/app/documents/{user_id}/`

### Files Changed

```text
app/Services/Documents/AIExtractionService.php           ← PDF text extraction, Haiku model, noise filter
app/Services/Documents/DocumentUploadService.php         ← 20MB limit, removed Excel/CSV, local storage fix
app/Http/Requests/Documents/UploadDocumentRequest.php    ← Updated validation rules
resources/js/components/Shared/UploadDropZone.vue        ← Removed Excel/CSV from UI
```

### Dependencies

```text
smalot/pdfparser  (installed via composer install on server)
```

---

## Files Changed Summary

### Frontend (6 files - Included in Build)

```text
resources/js/components/Retirement/RetirementIncomeTab.vue      ✅ Deployed
resources/js/components/Preview/PersonaSelectionModal.vue       ✅ Deployed
resources/js/components/Retirement/CapitalAdequacyTab.vue       ✅ Deployed (NEW)
resources/js/components/NetWorth/PensionList.vue                ✅ Deployed
resources/js/components/UserProfile/LetterToSpouse.vue          ✅ Deployed
resources/js/components/Shared/UploadDropZone.vue               ✅ Deployed
```

### Backend (6 files - Manual Upload)

```text
database/migrations/2026_02_02_095622_add_additional_boxes_to_letters_to_spouse_table.php  ✅ Deployed (NEW)
app/Models/LetterToSpouse.php                                                              ✅ Deployed
database/seeders/PreviewUserSeeder.php                                                     ✅ Deployed
app/Services/Documents/AIExtractionService.php                                             ✅ Deployed
app/Services/Documents/DocumentUploadService.php                                           ✅ Deployed
app/Http/Requests/Documents/UploadDocumentRequest.php                                      ✅ Deployed
```

### Persona Data (4 JSON files - Included in Build)

```text
resources/js/data/personas/peak_earners.json                    ✅ Deployed
resources/js/data/personas/entrepreneur.json                    ✅ Deployed
resources/js/data/personas/widow.json                           ✅ Deployed
resources/js/data/personas/retired_couple.json                  ✅ Deployed
```

---

## Rebuild Required: DONE

All frontend and backend changes have been deployed.

---

## Upload Checklist - COMPLETED ✅

### Step 1: Run Build ✅

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets ✅

Uploaded `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload PHP Files ✅

Uploaded 3 backend files via SiteGround File Manager:

```text
app/Services/Documents/AIExtractionService.php        → ~/www/fynla.org/public_html/app/Services/Documents/
app/Services/Documents/DocumentUploadService.php      → ~/www/fynla.org/public_html/app/Services/Documents/
app/Http/Requests/Documents/UploadDocumentRequest.php → ~/www/fynla.org/public_html/app/Http/Requests/Documents/
```

### Step 4: SSH Commands ✅

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Installed smalot/pdfparser dependency
composer install --no-dev --optimize-autoloader

# Cleared all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

### Deployment Fix

Initial deployment failed with S3 storage error (`Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found`). Fixed by changing `DocumentUploadService.php` to use local storage instead of S3.

---

## Verification

After deployment, verify:

1. **Agentic AI Info Banner** ✅
   - Navigate to Retirement > Income Planner tab
   - Verify blue informational banner appears at the top (below the header)
   - Verify the message about Agentic AI scaffolding is displayed correctly

2. **Persona Modal Register Message** ✅
   - Click "Get Started" or "Try Demo" on the landing page
   - Verify the message below the persona cards reads: "We strongly recommend looking through a persona to see the full power of the platform."
   - Verify the "Create Your Account" button links to /register

3. **Capital Adequacy Tab** ✅
   - Navigate to Retirement (Pensions tab)
   - Click on the "Capital Adequacy Planner" card
   - Verify the tab opens with back button, summary cards, allowance progress, slider, and capital progress
   - Verify carry forward shows 3 year breakdown with total
   - Verify contributions display correctly (including percentage-based occupational pensions)
   - Move the slider and verify impact calculations update
   - Verify slider constraint note shows whether limited by affordability or allowance

4. **Letter to Spouse** ✅
   - Navigate to User Profile > Letter to Spouse (or Expression of Wishes for single users)
   - Verify executor details show from Will data with "From Will" badge
   - Verify immediate actions display as numbered two-column checklist
   - Verify Financial Overview shows Pensions section with DC/DB badges
   - **Verify Bequests & Legacies section appears with "From Will" badge**
   - **Verify bequests show beneficiary name, type badge (Share/Legacy/Asset), and value**
   - **Verify conditions display in italics where applicable**
   - Verify all cards have white backgrounds (not grey)
   - Verify savings, pensions, and properties cards display inline
   - **Verify "Print / Save PDF" button appears next to Edit button**
   - **Click "Print / Save PDF" and verify new window opens with clean letter content**
   - **Verify no browser headers/footers (date, URL) appear on the printout**
   - **Verify Fynla logo appears large (120px) in top right corner**
   - **Verify PDF contains all sections: Immediate Actions, Financial Overview, Additional Info, Final Wishes**
   - **Verify PDF includes bequests section**
   - **Verify subsequent pages have proper top margin spacing**
   - **Verify print window closes automatically after printing**
   - Click Edit, then click "Add Additional Information Box"
   - Verify box appears with title and content fields
   - Verify counter shows "1/10"
   - Add a second box, verify counter updates to "2/10"
   - Remove a box using the trash icon, verify it disappears
   - Save and verify additional boxes persist

5. **Preview Persona Letter to Spouse Data** ✅
   - Test with Mitchells (peak_earners) persona:
     - Navigate to User Profile > Letter to Spouse
     - Verify David has: James Patterson as financial adviser, Henderson & Co as solicitor, Graham & Associates as accountant
     - Verify immediate actions are populated
     - Verify Funeral section shows "Cremation" preference
     - Verify 3 additional boxes appear (Manchester BTL, Education Trust, NHS Pension)
     - **Verify Bequests section shows: William (50%), Charlotte (50%), Cancer Research UK (£10k)**
     - Click spouse toggle to view Sarah's letter
     - Verify Sarah has her own letter with 1 additional box
     - **Verify Sarah's bequests: William (50%), Charlotte (50%), British Heart Foundation (£10k)**
   - Test with Alex Chen (entrepreneur) persona:
     - Navigate to User Profile > Expression of Wishes (single user)
     - Verify: Interactive Investor as adviser, Pannone Corporate as solicitor, BDO as accountant
     - Verify 4 additional boxes (business succession info)
     - **Verify Bequests: Parents (40% each), Manchester Tech Foundation (10%), Cancer Research UK (£25k), Tom Harrison asset**
   - Test with Margaret Thompson (widow) persona:
     - Verify: Helen Richards as adviser, Smithson Solicitors
     - Verify burial preference (not cremation)
     - Verify 5 additional boxes (trust, properties, etc.)
     - **Verify Bequests: Andrew (40%), Catherine (40%), Grandchildren Trust (15%), Cotswold Hospice (£25k), St Lawrence Church (£5k), Rose Cottage asset**
   - Test with Bennetts (retired_couple) persona:
     - Verify both Patricia and Harold have letters
     - Verify Adams & Co as solicitor for both
     - Verify Patricia has 4 additional boxes, Harold has 1
     - **Verify Bequests: Mark (50%), Susan (50%), Grandchildren Education Fund (£25k)**

6. **Document Upload Feature** ✅
   - Navigate to Retirement (Pensions tab)
   - Click "Upload Statement" button
   - Verify supported formats shows "PDF, PNG, JPG, WebP (max 20MB)" - NO Excel/CSV
   - Verify Excel icon no longer appears when selecting files
   - **Test with text-based PDF**:
     - Upload a PDF with selectable text (e.g., digital pension statement)
     - Should process via text extraction (faster, cheaper)
     - Check Laravel logs for "[AIExtractionService] PDF has extractable text"
   - **Test with scanned PDF**:
     - Upload a scanned PDF (no selectable text)
     - Should fall back to vision API
     - Check logs for "[AIExtractionService] PDF appears to be scanned"
   - **Test with image**:
     - Upload a PNG/JPG pension statement
     - Should process via vision API
   - **Test file size limit**:
     - Try uploading a file > 20MB
     - Should show "File too large. Maximum size is 20MB."
   - Verify extracted data appears in review modal with confidence badges

7. **Existing Functionality**
   - Verify all existing features still work

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---
