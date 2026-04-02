# Excel Holdings Import — Design Spec

**Date:** 2 April 2026
**Status:** Approved
**Branch:** uploads

---

## Problem

Users have investment and pension holdings data in platform exports (Hargreaves Lansdown, AJ Bell, Vanguard, etc.) and personal spreadsheets. Currently they must manually enter each holding one by one. There is no way to bulk-import holdings or upload Excel files.

## Solution

Extend the existing `DocumentUploadModal` to accept Excel workbooks (.xlsx, .xls, .csv). The system processes each sheet independently via AI extraction, classifies what area of the app each sheet belongs to, auto-matches to existing accounts, and presents a single review screen where the user confirms everything at once.

---

## Scope

**In scope:**
- Accept .xlsx, .xls, .csv in the existing DocumentUploadModal
- AI extraction per sheet — identify account type, extract holdings
- Sheet classification: investment holdings, pension holdings, cash/savings, or skip
- Auto-match sheets to existing user accounts (or propose "create new")
- Holdings diff review: add, update, unchanged, not-in-import
- Bulk save of accounts + holdings from one upload
- Works for both structured platform exports and freeform user spreadsheets

**Out of scope:**
- Batch multi-file upload (one file at a time)
- Automatic recurring imports / sync with platforms
- DB pension or state pension holdings (they don't have holdings)

---

## Architecture

### Flow

```
User drops .xlsx/.xls/.csv into DocumentUploadModal
    |
    v
Backend: ExcelParserService converts each sheet to text
    |
    v
Backend: AIExtractionService.extractFromExcel()
    - Processes each sheet independently (1 AI call per sheet)
    - AI classifies: investment_holdings | pension_holdings | cash_savings | property | protection | ignore
    - AI extracts: account metadata + holdings array (or entity fields for cash/property/protection)
    |
    v
Backend: Returns per-sheet extraction results to frontend
    |
    v
Frontend: New "Sheet Review" step in DocumentUploadModal
    - Lists each sheet with detected category + confidence
    - Auto-matched to existing account (or "Create new")
    - User can override category and account match
    - Shows holdings per sheet with diff indicators
    - User confirms all sheets at once
    |
    v
Backend: DocumentProcessor.processExcelWorkbook()
    - Creates new accounts where needed
    - Saves holdings via HoldingsMapper (add/update per user selection)
    - Records Document + DocumentExtraction for audit trail
```

### Limits

- Max 10 sheets per workbook
- Max 500 rows per sheet
- Max 26 columns per sheet
- Max file size: 20MB (existing limit)

---

## Backend Changes

### 1. UploadDocumentRequest

Add Excel MIME types to accepted files:

```php
'document' => 'required|file|mimes:pdf,jpeg,png,webp,xlsx,xls,csv|max:20480'
```

Add new document type:

```php
'document_type' => '...existing...|holdings_import'
```

### 2. ExcelParserService (existing, extend)

Currently converts sheets to text. Extend to:
- Return sheet names alongside content (currently just returns text)
- Return structured output: `[['name' => 'ISA', 'content' => '...', 'row_count' => 150], ...]`
- Skip empty sheets
- Cap at 10 sheets

### 3. AIExtractionService — new extractFromExcel() method

Processes each sheet independently. One AI call per sheet.

**Per-sheet prompt asks the AI to return:**

```json
{
  "sheet_name": "ISA",
  "category": "investment_holdings|pension_holdings|cash_savings|ignore",
  "category_confidence": 0.95,
  "account": {
    "provider": "Hargreaves Lansdown",
    "account_type": "isa",
    "account_number": "12345678",
    "total_value": 95000.00
  },
  "holdings": [
    {
      "security_name": "Vanguard FTSE All-World UCITS ETF",
      "ticker": "VWRL",
      "isin": "IE00B3RBWM25",
      "asset_type": "etf",
      "quantity": 150.5,
      "current_price": 85.20,
      "current_value": 12822.60,
      "purchase_price": null,
      "cost_basis": null
    }
  ],
  "confidence": {
    "provider": 0.95,
    "account_type": 0.90,
    "holdings": 0.85
  },
  "warnings": []
}
```

For `cash_savings` category, `holdings` is empty and `account` contains savings-specific fields (balance, interest_rate, access_type).

For `property` category, `holdings` is empty and `account` contains property fields (address, current_value, property_type, ownership_type, rental_income, mortgage_balance).

For `protection` category, `holdings` is empty and `account` contains policy fields (provider, policy_type, sum_assured, monthly_premium, start_date, term_years, cover_type).

For `ignore` category (Summary, Notes, T&Cs sheets), minimal response — just the classification.

**Sheet classification signals:**

| Category | Sheet name signals | Content signals |
|----------|-------------------|-----------------|
| investment_holdings | ISA, GIA, Stocks, Investments, Portfolio, Equities | Ticker, Units, Price, ISIN columns |
| pension_holdings | SIPP, Pension, Retirement, DC Pension | Same as above + pension context |
| cash_savings | Cash, Current Account, Savings, Easy Access, Deposit | Balance, Interest Rate, Sort Code |
| property | Property, Properties, Real Estate, House, Flat | Address, Value, Mortgage, Rental Income |
| protection | Insurance, Policies, Life Cover, Protection, Critical Illness | Sum Assured, Premium, Policy Number, Cover Type |
| ignore | Summary, Notes, Cover, Disclaimer, T&Cs, Fees | No financial data rows |

The AI uses both sheet name and content to classify. Content wins over name when they conflict.

### 4. New HoldingsMapper

Handles the merge logic when importing to an existing account:

**Matching priority:**
1. ISIN (exact match)
2. Ticker (exact match, case-insensitive)
3. Security name (fuzzy — AI-assisted normalisation at extraction time)

**Diff output per holding:**
- `add` — not found in existing holdings
- `update` — matched, quantity or value differs
- `unchanged` — matched, no meaningful difference
- `not_in_import` — exists in Fynla but not in the spreadsheet

The mapper does NOT auto-remove holdings not in the import. Those are shown to the user as "not in import" — the user must explicitly choose to remove them.

### 5. Account Auto-Matching

When the AI extracts an account type and provider for a sheet:

1. Query user's existing accounts of that type (e.g., all InvestmentAccounts where `account_type = 'isa'`)
2. If exactly one match by type + provider → auto-select it
3. If multiple matches → auto-select best match (type + provider), let user override
4. If no match → default to "Create new", pre-filled with extracted metadata

For pension sheets: same logic against DCPension records.
For property sheets: match against Property records by address similarity.
For protection sheets: match against protection policy records by policy type + provider.

### 6. DocumentProcessor — new processExcelWorkbook() method

Orchestrates the full flow:
1. Receives confirmed sheet mappings from frontend
2. For each sheet:
   - If "Create new": create InvestmentAccount/DCPension/SavingsAccount with extracted metadata
   - If matched to existing: load that account
   - Apply holdings via HoldingsMapper (add new, update existing, remove if user selected)
3. Save Document + DocumentExtraction records for audit
4. Wrap in DB transaction

### 7. New API Endpoints

```
POST /api/documents/upload           — existing, now accepts Excel
GET  /api/documents/{id}/extraction  — existing, extended to return per-sheet data
POST /api/documents/{id}/confirm     — existing, extended to accept sheet mappings
```

No new endpoints needed. The existing endpoints are extended with additional response/request fields when `document_type` is Excel-based.

---

## Frontend Changes

### 1. DocumentUploadModal — accept Excel

- Add `.xlsx, .xls, .csv` to `UploadDropZone` accepted types
- Detect file type after upload response
- If Excel: show new Sheet Review step instead of existing single-entity review

### 2. New Step: Sheet Review (inside DocumentUploadModal)

Appears after processing, before confirm. One screen showing all sheets.

**Per sheet row:**
- Sheet name (from Excel)
- Detected category badge: "Investment Holdings" / "Pension Holdings" / "Cash & Savings" / "Property" / "Protection" / "Skip"
- Category override dropdown
- Account match dropdown: lists existing accounts of that type + "Create new account"
- Expand to show holdings table

**Holdings table per sheet (expandable):**
- Columns: Security Name, Ticker/ISIN, Quantity, Price, Value
- If matched to existing account: diff badge per row (New / Updated / Unchanged / Not in Import)
- "Not in Import" rows shown at bottom with muted styling, checkbox to remove
- Row-level edit for corrections before confirm

**Confirm button:** "Import All" — saves everything at once

### 3. New HoldingsReviewTable component

Reusable component showing holdings with diff indicators. Used inside the Sheet Review step.

Props:
- `holdings` — array of extracted holdings
- `existingHoldings` — array of current holdings (for diff) or null (for new accounts)
- `editable` — whether user can edit values inline

Emits:
- `update:holdings` — when user edits a value
- `remove` — when user marks a "not in import" holding for removal

---

## Data Models

No new models needed. Uses existing:
- `Document` — tracks the uploaded file
- `DocumentExtraction` — stores per-sheet extraction results (one per sheet, linked to same Document)
- `Holding` — polymorphic, already supports InvestmentAccount and DCPension
- `InvestmentAccount` — created if "Create new" for investment sheets
- `DCPension` — created if "Create new" for pension sheets
- `SavingsAccount` — created if "Create new" for cash sheets
- `Property` — created if "Create new" for property sheets
- `LifeInsurancePolicy` / `CriticalIllnessPolicy` / `IncomeProtectionPolicy` — created if "Create new" for protection sheets

No schema changes required. The `DocumentExtraction.extracted_fields` JSON column stores the per-sheet data including holdings arrays.

---

## Error Handling

- **Empty/corrupt Excel:** Show "This file couldn't be read. Please check it's a valid spreadsheet."
- **No extractable sheets:** Show "No financial data found in this workbook."
- **AI extraction fails for one sheet:** Show that sheet with error state, allow user to skip it and continue with others
- **Account creation fails:** Transaction rolls back that sheet only, others proceed
- **Duplicate import detection:** If same file hash uploaded recently, warn "This file was uploaded on [date]. Import again?"

---

## Testing Plan

- Upload HL-style workbook with ISA + SIPP + GIA sheets → all three detected and matched
- Upload single-sheet CSV with custom columns → AI maps columns correctly
- Upload to account with existing holdings → diff shows correctly
- Upload workbook with "Summary" + "Notes" sheets → auto-skipped
- Upload workbook where one sheet fails extraction → others still importable
- Upload .xls (legacy format) → works same as .xlsx
- User overrides auto-matched account → holdings diff recalculates
- User selects "Create new" → account created with correct type and provider
- "Not in import" holdings shown but not removed unless user opts in
- Upload workbook with "Properties" sheet → property records created/matched
- Upload workbook with "Insurance" sheet → protection policies created/matched by type
