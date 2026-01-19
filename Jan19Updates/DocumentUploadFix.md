# Document Upload Fix for Preview Users

**Date:** 19 January 2026
**Branch:** `uploadFix`
**Commit:** `acc129b`

## Issue

The document upload feature was not working for preview users. When attempting to upload a pension statement or other financial document, the AI extraction via Anthropic API was not being called.

## Root Cause Analysis

Two separate issues were identified using systematic debugging:

### 1. Backend: PreviewWriteInterceptor Middleware

**Location:** `app/Http/Middleware/PreviewWriteInterceptor.php`

The middleware intercepts ALL POST/PUT/PATCH/DELETE requests for preview users and returns a fake success response without actually processing the request. This was preventing document uploads from reaching the `DocumentController` and the Anthropic API.

**Evidence:** API test returned:
```json
{
  "success": true,
  "message": "Preview: Record created (not saved)",
  "preview_mode": true,
  "data": {"id": "preview_xxx"}
}
```
Instead of actual extracted data from the AI.

### 2. Frontend: v-preview-disabled Directive

**Location:** Multiple Vue components

The `v-preview-disabled="'upload'"` directive was applied to all upload buttons, which:
- Disabled the button
- Showed "Register to upload data" tooltip
- Prevented click events from firing

## Solution

### Backend Fix

Added document upload routes to the exclusion lists in `PreviewWriteInterceptor.php`:

```php
private const EXCLUDED_ROUTES = [
    // ... existing routes ...
    'api/documents/upload',      // Allow document upload & AI extraction
    'api/documents/upload-only', // Allow document upload without extraction
];

private const EXCLUDED_PATTERNS = [
    // ... existing patterns ...
    '/reprocess',                // Document re-extraction endpoint
];
```

### Frontend Fix

Removed `v-preview-disabled="'upload'"` from upload buttons in:

| File | Component |
|------|-----------|
| `views/Retirement/RetirementReadiness.vue` | Upload Statement button |
| `components/NetWorth/PensionList.vue` | Upload Statement button |
| `components/Investment/PortfolioOverview.vue` | Upload Statement button |
| `components/Savings/CurrentSituation.vue` | Upload Statement button |
| `components/Protection/CurrentSituation.vue` | Upload Document button |

## Verification

### API Test (curl)
```bash
curl -X POST "http://localhost:8000/api/documents/upload" \
  -H "Authorization: Bearer TOKEN" \
  -F "document=@pension.pdf" \
  -F "document_type=pension_statement"
```

**Result:** Successfully returned extracted data:
- Provider: Aviva
- Pension type: personal
- Current fund value: £3,291.54
- Field confidence scores
- Validation warnings

### Browser Test
- Logged in as preview user (Emily & James Carter)
- Navigated to Pensions page
- Clicked "Upload Statement" button
- Modal opened successfully with drag-and-drop interface

## Files Changed

| File | Changes |
|------|---------|
| `app/Http/Middleware/PreviewWriteInterceptor.php` | +3 lines (added exclusions) |
| `resources/js/views/Retirement/RetirementReadiness.vue` | Removed directive |
| `resources/js/components/NetWorth/PensionList.vue` | Removed directive |
| `resources/js/components/Investment/PortfolioOverview.vue` | Removed directive |
| `resources/js/components/Savings/CurrentSituation.vue` | Removed directive |
| `resources/js/components/Protection/CurrentSituation.vue` | Removed directive |

## Notes

- The `confirm` endpoint (`POST /api/documents/{id}/confirm`) remains blocked for preview users, preventing them from permanently saving extracted data to the database
- Document uploads will still create `Document` and `DocumentExtraction` records, but these are tied to preview user accounts
- Anthropic API calls are made for preview users, which will consume API tokens
