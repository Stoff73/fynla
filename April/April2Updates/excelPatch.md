# Excel Holdings Import — Patch Notes

**Date:** 2 April 2026
**Branch:** uploads
**Status:** Browser tested on localhost, not deployed

---

## What's New

Users can now upload Excel workbooks (.xlsx, .xls) and CSV files through the existing "Upload Statement" button. The system processes each sheet independently via AI, classifies what area of the app it belongs to, auto-matches to existing accounts, and presents a single review screen where the user confirms everything at once.

### Supported Sheet Types

| Category | Example Sheet Names | Routes To |
|----------|-------------------|-----------|
| Investment Holdings | ISA, GIA, Stocks, Portfolio | InvestmentAccount + Holdings |
| Pension Holdings | SIPP, Pension, Retirement | DCPension + Holdings |
| Cash & Savings | Cash, Current Account, Savings | SavingsAccount |
| Property | Properties, Real Estate | Property |
| Protection | Insurance, Life Cover, Policies | LifeInsurance / CriticalIllness / IncomeProtection |
| Ignore (auto-skipped) | Summary, Notes, T&Cs | Skipped |

### How It Works

1. User clicks "Upload Statement" on any module page
2. Drops an Excel file (or PDF/image — existing flow unchanged)
3. Each sheet is sent to Grok AI independently for classification and extraction
4. Sheet Review step shows all sheets with:
   - Detected category (user can override via dropdown)
   - Auto-matched existing account (user can override or create new)
   - Holdings table with diff indicators (New / Updated / No Change / Not in Import)
5. User clicks "Import All" — accounts created, holdings saved

### Holdings Diff

When importing to an account that already has holdings, the system shows:
- **New** (green) — holding not in Fynla, will be added
- **Updated** (blue) — matched by ISIN/ticker, quantity or value changed
- **No Change** (grey) — matched, no differences
- **Not in Import** (muted) — exists in Fynla but not in spreadsheet, with optional remove checkbox

Holdings are matched by ISIN first, then ticker, then security name (fuzzy).

### Limits

- Max 10 sheets per workbook
- Max 500 rows per sheet
- Max 20MB file size

---

## Files Changed

### New Files (8)

| File | Purpose |
|------|---------|
| `app/Services/Documents/HoldingsImportService.php` | Account matching, holdings diff, bulk save |
| `app/Services/Documents/FieldMappers/PropertyMapper.php` | Property field mapping |
| `app/Services/Documents/FieldMappers/ProtectionMapper.php` | Protection policy field mapping |
| `app/Services/Documents/FieldMappers/SavingsAccountMapper.php` | Savings account field mapping |
| `app/Services/Documents/FieldMappers/MortgageMapper.php` | Mortgage field mapping |
| `resources/js/components/Shared/SheetReviewStep.vue` | Sheet review UI (categories, account match, holdings) |
| `resources/js/components/Shared/HoldingsReviewTable.vue` | Holdings diff table with status badges |
| `resources/js/services/documentService.js` | Added `confirmExcel()` API call |

### Modified Files (7)

| File | Change |
|------|--------|
| `app/Services/Documents/ExcelParserService.php` | New `parseToSheets()` for per-sheet structured data |
| `app/Services/Documents/AIExtractionService.php` | New `extractSheet()` + Excel classification prompt |
| `app/Services/Documents/DocumentProcessor.php` | `processExcel()` + `confirmExcel()` orchestration |
| `app/Services/Documents/DocumentUploadService.php` | Excel MIME types + file extensions |
| `app/Http/Controllers/Api/DocumentController.php` | Excel-aware upload + `confirmExcel` endpoint |
| `app/Http/Requests/Documents/UploadDocumentRequest.php` | Accept xlsx/xls/csv |
| `routes/api.php` | `POST /api/documents/{id}/confirm-excel` route |

### Frontend Modified (2)

| File | Change |
|------|--------|
| `resources/js/components/Shared/DocumentUploadModal.vue` | Excel detection, sheet-review step, confirmExcel handler |
| `resources/js/components/Shared/UploadDropZone.vue` | Excel MIME types, Excel icon, updated help text |

### Tests (5 files, 21 tests)

| File | Tests |
|------|-------|
| `tests/Unit/Services/Documents/ExcelParserServiceTest.php` | parseToSheets, skip empty, cap at 10 |
| `tests/Unit/Services/Documents/AIExtractionServiceTest.php` | Prompt content, method signature |
| `tests/Unit/Services/Documents/FieldMappers/PropertyMapperTest.php` | Field mapping, validation |
| `tests/Unit/Services/Documents/FieldMappers/ProtectionMapperTest.php` | Field mapping, model class detection |
| `tests/Unit/Services/Documents/HoldingsImportServiceTest.php` | Account matching, diff, apply |
| `tests/Feature/Documents/ExcelUploadTest.php` | Upload acceptance, rejection, auth |

---

## Browser Test Results (localhost)

- [x] Upload Statement modal shows "Supported: PDF, images, or Excel spreadsheets"
- [x] Excel file icon (green) shown when .xlsx selected
- [x] File accepted and uploaded (no validation rejection)
- [x] Processing spinner shown during AI extraction
- [x] Sheet Review step renders with 3 sheets: ISA, SIPP, Cash
- [x] ISA classified as "Investment Holdings" with correct holdings (VWRL, IWDA)
- [x] SIPP classified as "Pension Holdings" with correct funds (L&G, Vanguard LS80)
- [x] Cash classified as "Cash & Savings" with Marcus account
- [x] Auto-matched ISA to existing Vanguard account with holdings diff
- [x] Auto-matched SIPP to existing Scottish Widows DC pension
- [x] Category dropdowns functional (Investment/Pension/Cash/Property/Protection/Skip)
- [x] Account match dropdown shows existing accounts + "Create new"
- [x] "Import 3 Sheets" button clicked — data saved to database
- [x] Holdings verified in DB: VWRL + IWDA on ISA, L&G + LS80 on pension

## Bugs Found & Fixed During Testing

1. **DocumentUploadService MIME validation** — `ALLOWED_MIME_TYPES` only had PDF/image, rejected xlsx. Fixed: added Excel MIME types.
2. **File extension** — `getExtensionFromMime()` defaulted to `.bin` for xlsx. Fixed: added xlsx/xls/csv mappings.
3. **Document type enum** — `holdings_import` not in DB enum. Fixed: use `Document::TYPE_UNKNOWN`.
4. **raw_response column** — `document_extractions.raw_response` has no default value. Fixed: include in create().
5. **PhpSpreadsheet v5** — `getCellByColumnAndRow()` removed in v5.3. Fixed: use `getCell()` with coordinate strings.
